<?php

namespace App\Service;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Entity\Notification;
use App\Entity\Part;
use App\Entity\User;
use App\Repository\FaultRepository;
use App\Repository\InterventionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class FaultWorkflow
{
    private const SYMPTOMS = [
        'Conveyor does not start or motor stops frequently' => [
            'causes' => ['motor overloaded', 'motor draws too much current'],
            'solutions' => ['check conveyor overload', 'check temperature/circuit breaker, replace if needed'],
        ],
        'Cracking noise at the return discs (disques de renvoi)' => [
            'causes' => ['defective bearing', 'defective housing/palier'],
            'solutions' => ['replace bearing', 'replace palier'],
        ],
        'Motor or reducer overheating' => [
            'causes' => ['conveyor overloaded', 'low voltage'],
            'solutions' => ['check capacity, reduce load to recommended value', 'have an electrician check'],
        ],
        'Rope stops but motor runs' => [
            'causes' => ['overload', 'rope not tensioned', 'rope blocked by an obstacle'],
            'solutions' => ['reduce load', 'tension the rope', 'remove obstacle'],
        ],
    ];

    public function __construct(
        private FaultRepository $faultRepository,
        private InterventionRepository $interventionRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, array{causes: list<string>, solutions: list<string>}> */
    public function troubleshooting(): array
    {
        return self::SYMPTOMS;
    }

    public function declare(
        User $reporter,
        Machine $machine,
        ?MachineModule $module,
        ?Part $part,
        string $symptom,
        string $description,
        string $severity,
        ?string $submissionToken = null,
    ): Fault {
        if ($submissionToken !== null) {
            $existingFault = $this->faultRepository->findOneBy(['submissionToken' => $submissionToken]);
            if ($existingFault instanceof Fault) {
                return $existingFault;
            }
        }

        if ($symptom !== 'autre' && !array_key_exists($symptom, self::SYMPTOMS)) {
            throw new InvalidArgumentException('Le symptôme sélectionné est invalide.');
        }
        if ($description === '' || !in_array($severity, ['critique', 'moyenne', 'faible'], true)) {
            throw new InvalidArgumentException('La description et la gravité sont obligatoires.');
        }
        if ($module !== null && $part !== null && $part->getModule() !== $module) {
            throw new InvalidArgumentException('La pièce sélectionnée ne correspond pas au module.');
        }
        if ($module !== null && $module->getMachineModel() !== $machine->getMachineModel()) {
            throw new InvalidArgumentException('Le module sélectionné ne correspond pas au modèle de la machine.');
        }
        if ($part !== null && $part->getModule()?->getMachineModel() !== $machine->getMachineModel()) {
            throw new InvalidArgumentException('La pièce sélectionnée ne correspond pas au modèle de la machine.');
        }

        $fault = (new Fault())
            ->setMachine($machine)
            ->setReportedBy($reporter)
            ->setReportedAt(new \DateTime())
            ->setSymptom($symptom === 'autre' ? null : $symptom)
            ->setDescription($description)
            ->setSeverity($severity)
            ->setStatus('ouverte')
            ->setModule($module)
            ->setPart($part)
            ->setSubmissionToken($submissionToken);
        if ($severity === 'critique') {
            $machine->setStatus('en panne');
        }

        $intervention = (new Intervention())
            ->setFault($fault)
            ->setType('curative')
            ->setStartDate(new \DateTime())
            ->setStatus('planifiee')
            ->setAssignmentSource('systeme');
        $technician = $this->findTechnician($machine);
        if ($technician !== null) {
            $intervention->setTechnician($technician);
        }

        $this->entityManager->persist($fault);
        $this->entityManager->persist($intervention);
        foreach ($this->responsablesFor($machine) as $responsable) {
            $this->notify($responsable, sprintf('Nouvelle panne %s déclarée sur %s.', $severity, $machine), 'panne', $fault, $intervention);
        }
        if ($technician !== null) {
            $this->notify($technician, sprintf('Une intervention vous est affectée sur %s.', $machine), 'intervention', $fault, $intervention);
        }
        $this->entityManager->flush();

        return $fault;
    }

    private function findTechnician(Machine $machine): ?User
    {
        $site = $machine->getSite();
        if ($site === null) {
            return null;
        }
        $technicians = array_values(array_filter(
            $this->userRepository->findBy(['site' => $site]),
            static fn (User $user): bool => in_array('ROLE_TECHNICIEN', $user->getRoles(), true)
        ));
        usort($technicians, fn (User $a, User $b): int => $this->interventionRepository->countOpenForTechnician($a) <=> $this->interventionRepository->countOpenForTechnician($b));

        return $technicians[0] ?? null;
    }

    /** @return list<User> */
    private function responsablesFor(Machine $machine): array
    {
        $site = $machine->getSite();
        if ($site === null) {
            return [];
        }

        return array_values(array_filter(
            $this->userRepository->findBy(['site' => $site]),
            static fn (User $user): bool => in_array('ROLE_RESPONSABLE', $user->getRoles(), true)
        ));
    }

    private function notify(User $recipient, string $message, string $type, Fault $fault, Intervention $intervention): void
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
}
