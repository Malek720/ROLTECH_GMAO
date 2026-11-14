<?php

namespace App\Service;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\InterventionPart;
use App\Entity\MaintenanceSchedule;
use App\Entity\Notification;
use App\Entity\Part;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InterventionWorkflow
{
    /** @var array<string, string> */
    public const LOCKOUT_ITEMS = [
        'electrical' => 'Alimentation électrique coupée et verrouillée',
        'pneumatic' => 'Alimentation pneumatique coupée et verrouillée',
        'qualified' => 'Personnel qualifié et pièces autorisées confirmés',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {
    }

    /** @param array<int|string, mixed> $lockoutItems */
    public function start(Intervention $intervention, User $technician, array $lockoutItems): void
    {
        $this->assertAssignedTo($intervention, $technician);
        if ($intervention->getStatus() !== 'planifiee') {
            throw new \InvalidArgumentException('Cette intervention ne peut plus être démarrée.');
        }

        $checked = array_fill_keys(array_keys(self::LOCKOUT_ITEMS), false);
        foreach ($lockoutItems as $item) {
            if (is_string($item) && array_key_exists($item, $checked)) {
                $checked[$item] = true;
            }
        }
        if (in_array(false, $checked, true)) {
            throw new \InvalidArgumentException('Toutes les règles de consignation doivent être confirmées.');
        }

        $now = new \DateTime();
        $intervention
            ->setStatus('en cours')
            ->setStartedAt($now)
            ->setLockoutConfirmedAt($now);
        $this->entityManager->flush();
    }

    /** @param array<int|string, mixed> $checkedSteps */
    public function updateChecklist(Intervention $intervention, User $technician, array $checkedSteps): void
    {
        $this->assertAssignedTo($intervention, $technician);
        if ($intervention->getStatus() !== 'en cours') {
            throw new \InvalidArgumentException('La checklist ne peut être modifiée que pendant une intervention en cours.');
        }

        $steps = $this->procedureSteps($intervention);
        $progress = [];
        foreach ($steps as $index => $step) {
            $progress[(string) $index] = in_array((string) $index, array_map('strval', $checkedSteps), true);
        }
        $intervention->setChecklistProgress($progress);
        $this->entityManager->flush();
    }

    /**
     * @param array<int|string, mixed> $parts
     */
    public function complete(Intervention $intervention, User $technician, string $comment, array $parts = []): void
    {
        $this->assertAssignedTo($intervention, $technician);
        if ($intervention->getStatus() !== 'en cours' || $intervention->getStartedAt() === null) {
            throw new \InvalidArgumentException('Une intervention doit être démarrée avant d’être terminée.');
        }
        if (trim($comment) === '') {
            throw new \InvalidArgumentException('Un commentaire de fin est obligatoire.');
        }
        if (!$this->hasCompletedProcedure($intervention)) {
            throw new \InvalidArgumentException('Toutes les étapes de la procédure doivent être cochées.');
        }

        foreach ($parts as $partId => $quantity) {
            $part = $this->entityManager->getRepository(Part::class)->find((int) $partId);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
            if (!$part instanceof Part || $quantity === false || $quantity < 1 || !$this->partBelongsToMachine($intervention, $part)) {
                throw new \InvalidArgumentException('Une pièce remplacée est invalide pour cette machine.');
            }

            $line = null;
            foreach ($intervention->getParts() as $existing) {
                if ($existing->getPart() === $part) {
                    $line = $existing;
                    break;
                }
            }
            if ($line === null) {
                $line = (new InterventionPart())->setIntervention($intervention)->setPart($part);
                $intervention->addPart($line);
                $this->entityManager->persist($line);
            }
            $line->setQuantity($quantity);
        }

        $intervention
            ->setComment(trim($comment))
            ->setEndDate(new \DateTime())
            ->setStatus('terminee');
        if ($intervention->getSchedule() instanceof MaintenanceSchedule) {
            $this->completeSchedule($intervention->getSchedule());
        } elseif ($intervention->getFault() instanceof Fault) {
            $this->resolveFaultIfComplete($intervention->getFault(), $intervention);
        }
        $this->entityManager->flush();
    }

    public function cannotIntervene(Intervention $intervention, User $technician, string $reason): void
    {
        $this->assertAssignedTo($intervention, $technician);
        if (!in_array($intervention->getStatus(), ['planifiee', 'en cours'], true)) {
            throw new \InvalidArgumentException('Cette intervention n’est plus disponible pour un retour au dispatch.');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('La raison est obligatoire.');
        }

        $intervention->setTechnician(null);
        $site = $this->machineFor($intervention)?->getSite();
        if ($site !== null) {
            foreach ($this->userRepository->findBy(['site' => $site]) as $responsable) {
                if (in_array('ROLE_RESPONSABLE', $responsable->getRoles(), true)) {
                    $this->addNotification($responsable, 'Intervention renvoyée au dispatch : '.trim($reason), $intervention);
                }
            }
        }
        $this->entityManager->flush();
    }

    /** @return list<string> */
    public function procedureSteps(Intervention $intervention): array
    {
        $checklist = $intervention->getSchedule()?->getTask()?->getProcedureChecklist();
        if ($checklist === null || trim($checklist) === '') {
            return [];
        }

        $decoded = json_decode($checklist, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(
                static fn (mixed $step): string => is_array($step) ? trim((string) ($step['label'] ?? '')) : trim((string) $step),
                $decoded
            )));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $checklist) ?: [])));
    }

    private function assertAssignedTo(Intervention $intervention, User $technician): void
    {
        if ($intervention->getTechnician() !== $technician || !in_array('ROLE_TECHNICIEN', $technician->getRoles(), true)) {
            throw new \LogicException('Cette intervention ne vous est pas affectée.');
        }
    }

    private function hasCompletedProcedure(Intervention $intervention): bool
    {
        foreach ($intervention->getChecklistProgress() as $checked) {
            if ($checked !== true) {
                return false;
            }
        }

        return count($intervention->getChecklistProgress()) === count($this->procedureSteps($intervention));
    }

    private function partBelongsToMachine(Intervention $intervention, Part $part): bool
    {
        return $part->getModule()?->getMachineModel() === $this->machineFor($intervention)?->getMachineModel();
    }

    private function completeSchedule(MaintenanceSchedule $schedule): void
    {
        $now = new \DateTime();
        $task = $schedule->getTask();
        $schedule->setLastDoneDate(new \DateTime($now->format('Y-m-d')));
        $schedule->setLastDoneHours($schedule->getMachine()?->getOperatingHours($now) ?? 0);
        $schedule->setNextDueDate($task?->getFrequencyDays() === null
            ? new \DateTime($now->format('Y-m-d'))
            : (clone $now)->modify(sprintf('+%d days', $task->getFrequencyDays())));
        $schedule->setNextDueHours($task?->getFrequencyHours() === null
            ? $schedule->getLastDoneHours()
            : $schedule->getLastDoneHours() + $task->getFrequencyHours());
        $schedule->setState('ok');
    }

    private function resolveFaultIfComplete(Fault $fault, Intervention $completed): void
    {
        foreach ($fault->getInterventions() as $intervention) {
            if ($intervention !== $completed && $intervention->getStatus() !== 'terminee') {
                return;
            }
        }
        $fault->setStatus('resolue');
        $recipients = [$fault->getReportedBy()];
        foreach ($this->userRepository->findBy(['site' => $fault->getMachine()?->getSite()]) as $user) {
            if (in_array('ROLE_RESPONSABLE', $user->getRoles(), true)) {
                $recipients[] = $user;
            }
        }
        foreach ($recipients as $recipient) {
            if ($recipient instanceof User) {
                $this->addNotification($recipient, 'La panne '.$fault.' est résolue.', $completed, $fault);
            }
        }
    }

    private function addNotification(User $recipient, string $message, Intervention $intervention, ?Fault $fault = null): void
    {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setMessage($message)
            ->setType('intervention')
            ->setCreatedAt(new \DateTime())
            ->setFault($fault)
            ->setIntervention($intervention);
        $this->entityManager->persist($notification);
    }

    private function machineFor(Intervention $intervention): ?\App\Entity\Machine
    {
        return $intervention->getFault()?->getMachine() ?? $intervention->getSchedule()?->getMachine();
    }
}
