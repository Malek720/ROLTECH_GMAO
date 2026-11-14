<?php

namespace App\Service;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Entity\MaintenancePostponement;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Notification;
use App\Entity\Part;
use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ResponsableWorkflow
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InterventionRepository $interventionRepository,
        private MaintenanceScheduleRepository $scheduleRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function assign(Intervention $intervention, User $responsable, User $technician): void
    {
        if ($intervention->getStatus() !== 'planifiee') {
            throw new \InvalidArgumentException('Seules les interventions planifiées peuvent être affectées ou réaffectées.');
        }
        if (!in_array('ROLE_TECHNICIEN', $technician->getRoles(), true)) {
            throw new \InvalidArgumentException('Sélectionnez un technicien valide.');
        }
        $machine = $this->machineFor($intervention);
        if ($machine === null || $technician->getSite() !== $machine->getSite()) {
            throw new \InvalidArgumentException('Le technicien doit appartenir au site de la machine.');
        }

        $intervention
            ->setTechnician($technician)
            ->setAssignedBy($responsable)
            ->setAssignmentSource('responsable');
        $this->addNotification($technician, 'Une intervention vous a été affectée : '.$this->sourceLabel($intervention), 'intervention', $intervention);
        $this->entityManager->flush();
    }

    public function createManual(
        User $responsable,
        Machine $machine,
        string $type,
        ?MaintenanceTask $task,
        ?MachineModule $module,
        ?Part $part,
        ?User $technician,
        string $description,
        string $severity,
    ): Intervention {
        if (!in_array($type, ['preventive', 'curative'], true)) {
            throw new \InvalidArgumentException('Le type d’intervention est invalide.');
        }
        $this->assertCatalogBelongsToMachine($machine, $module, $part);
        if ($technician !== null && $technician->getSite() !== $machine->getSite()) {
            throw new \InvalidArgumentException('Le technicien doit appartenir au site de la machine.');
        }

        $intervention = (new Intervention())
            ->setType($type)
            ->setStartDate(new \DateTime())
            ->setStatus('planifiee')
            ->setAssignmentSource('responsable')
            ->setAssignedBy($responsable)
            ->setTechnician($technician);

        if ($type === 'curative') {
            if (trim($description) === '' || !in_array($severity, ['critique', 'moyenne', 'faible'], true)) {
                throw new \InvalidArgumentException('La description et la gravité sont obligatoires pour une intervention curative.');
            }
            $fault = (new Fault())
                ->setMachine($machine)
                ->setReportedBy($responsable)
                ->setReportedAt(new \DateTime())
                ->setDescription(trim($description))
                ->setSeverity($severity)
                ->setStatus('ouverte')
                ->setModule($module)
                ->setPart($part)
                ->setSymptom('autre');
            $intervention->setFault($fault);
            $this->entityManager->persist($fault);
        } else {
            if (!$task instanceof MaintenanceTask || $task->getMachineModel() !== $machine->getMachineModel()) {
                throw new \InvalidArgumentException('Sélectionnez une tâche du modèle de cette machine.');
            }
            $schedule = $this->scheduleRepository->findActiveForMachineAndTask($machine, $task);
            if ($schedule === null) {
                $schedule = (new MaintenanceSchedule())->initializeFromMachine($machine, $task);
                $this->entityManager->persist($schedule);
            }
            $intervention->setSchedule($schedule);
        }

        $this->entityManager->persist($intervention);
        if ($technician !== null) {
            $this->addNotification($technician, 'Une intervention vous a été affectée : '.$this->sourceLabel($intervention), 'intervention', $intervention);
        }
        $this->entityManager->flush();

        return $intervention;
    }

    public function closeFault(Fault $fault, User $responsable): void
    {
        if ($fault->getStatus() === 'resolue') {
            throw new \InvalidArgumentException('Cette panne est déjà résolue.');
        }
        $fault->setStatus('resolue');
        $this->notifyFaultStakeholders($fault, 'La panne '.$fault.' a été clôturée par un responsable.');
        $this->entityManager->flush();
    }

    public function reopenFault(Fault $fault, User $responsable, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('La raison de réouverture est obligatoire.');
        }
        if ($fault->getStatus() !== 'resolue') {
            throw new \InvalidArgumentException('Seule une panne résolue peut être réouverte.');
        }
        $fault->setStatus('ouverte');
        $this->notifyFaultStakeholders($fault, 'La panne '.$fault.' a été réouverte : '.trim($reason));
        $this->entityManager->flush();
    }

    public function postpone(MaintenanceSchedule $schedule, User $responsable, \DateTime $newDate, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('La raison du report est obligatoire.');
        }
        $previousDate = $schedule->getNextDueDate();
        if ($previousDate === null || $newDate <= $previousDate) {
            throw new \InvalidArgumentException('La nouvelle date doit être postérieure à la date d’échéance actuelle.');
        }

        $postponement = (new MaintenancePostponement())
            ->setSchedule($schedule)
            ->setPostponedBy($responsable)
            ->setReason(trim($reason))
            ->setPreviousDueDate(clone $previousDate)
            ->setNewDueDate($newDate)
            ->setCreatedAt(new \DateTime());
        $schedule->setNextDueDate($newDate)->setState('ok');
        $this->entityManager->persist($postponement);
        $this->entityManager->flush();
    }

    /** @return list<array{technician: User, openCount: int}> */
    public function techniciansFor(Intervention $intervention): array
    {
        $machine = $this->machineFor($intervention);
        if ($machine?->getSite() === null) {
            return [];
        }
        $result = [];
        foreach ($this->userRepository->findTechniciansForSite($machine->getSite()) as $technician) {
            $result[] = [
                'technician' => $technician,
                'openCount' => $this->interventionRepository->countOpenForTechnician($technician),
            ];
        }
        usort($result, static fn (array $left, array $right): int => $left['openCount'] <=> $right['openCount']);

        return $result;
    }

    private function assertCatalogBelongsToMachine(Machine $machine, ?MachineModule $module, ?Part $part): void
    {
        if ($module !== null && $module->getMachineModel() !== $machine->getMachineModel()) {
            throw new \InvalidArgumentException('Le module sélectionné est invalide pour cette machine.');
        }
        if ($part !== null && ($part->getModule()?->getMachineModel() !== $machine->getMachineModel() || ($module !== null && $part->getModule() !== $module))) {
            throw new \InvalidArgumentException('La pièce sélectionnée est invalide pour cette machine.');
        }
    }

    private function notifyFaultStakeholders(Fault $fault, string $message): void
    {
        $recipients = [$fault->getReportedBy()];
        foreach ($this->userRepository->findBy(['site' => $fault->getMachine()?->getSite()]) as $user) {
            if (in_array('ROLE_RESPONSABLE', $user->getRoles(), true)) {
                $recipients[] = $user;
            }
        }
        foreach ($fault->getInterventions() as $intervention) {
            $recipients[] = $intervention->getTechnician();
        }
        foreach ($recipients as $recipient) {
            if ($recipient instanceof User) {
                $this->addNotification($recipient, $message, 'panne', $fault->getInterventions()->first() instanceof Intervention ? $fault->getInterventions()->first() : null, $fault);
            }
        }
    }

    private function addNotification(User $recipient, string $message, string $type, ?Intervention $intervention = null, ?Fault $fault = null): void
    {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setMessage($message)
            ->setType($type)
            ->setCreatedAt(new \DateTime())
            ->setFault($fault)
            ->setIntervention($intervention);
        $this->entityManager->persist($notification);
    }

    private function machineFor(Intervention $intervention): ?Machine
    {
        return $intervention->getFault()?->getMachine() ?? $intervention->getSchedule()?->getMachine();
    }

    private function sourceLabel(Intervention $intervention): string
    {
        return $this->machineFor($intervention).' — '.($intervention->getFault()?->getDescription() ?? $intervention->getSchedule()?->getTask()?->getAction());
    }
}
