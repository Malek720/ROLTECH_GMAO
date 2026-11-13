<?php

namespace App\Service;

use App\Entity\Intervention;
use App\Entity\MaintenanceSchedule;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MaintenancePlanner
{
    public function __construct(
        private MaintenanceScheduleRepository $scheduleRepository,
        private InterventionRepository $interventionRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Recomputes schedule states using calendar and operating-hour progress.
     * "Predictive" here means only 80%/100% usage thresholds; no ML is involved.
     *
     * @return array{updated: int, created: int, notified: int}
     */
    public function check(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        $updated = 0;
        $created = 0;
        $notified = 0;

        foreach ($this->scheduleRepository->findAllForPlanning() as $schedule) {
            $state = $this->calculateState($schedule, $now);
            if ($schedule->getState() !== $state) {
                $schedule->setState($state);
                $updated++;
            }

            if ($state !== 'echue' || $this->interventionRepository->hasOpenPreventiveForSchedule($schedule)) {
                continue;
            }

            $intervention = (new Intervention())
                ->setType('preventive')
                ->setStartDate(\DateTime::createFromImmutable($now))
                ->setStatus('planifiee')
                ->setSchedule($schedule)
                ->setAssignmentSource('systeme');
            $technician = $this->findTechnician($schedule);
            if ($technician !== null) {
                $intervention->setTechnician($technician);
                $this->addNotification($technician, 'Une intervention préventive est échue pour '.$schedule->getMachine(), 'maintenance', null, $intervention);
                $notified++;
            } else {
                foreach ($this->responsablesFor($schedule) as $responsable) {
                    $this->addNotification($responsable, 'Aucun technicien disponible pour la maintenance de '.$schedule->getMachine(), 'maintenance', null, $intervention);
                    $notified++;
                }
            }
            $this->entityManager->persist($intervention);
            $created++;
        }

        $this->entityManager->flush();

        return compact('updated', 'created', 'notified');
    }

    private function calculateState(MaintenanceSchedule $schedule, \DateTimeImmutable $now): string
    {
        $task = $schedule->getTask();
        $ratios = [];
        if ($task?->getFrequencyDays() !== null && $schedule->getLastDoneDate() !== null) {
            $elapsedDays = ($now->getTimestamp() - $schedule->getLastDoneDate()->getTimestamp()) / 86400;
            $ratios[] = $elapsedDays / $task->getFrequencyDays();
        }
        if ($task?->getFrequencyHours() !== null && $schedule->getLastDoneHours() !== null) {
            $elapsedHours = $schedule->getMachine()?->getOperatingHours(\DateTime::createFromImmutable($now)) - $schedule->getLastDoneHours();
            $ratios[] = $elapsedHours / $task->getFrequencyHours();
        }
        $progress = max($ratios ?: [0]);

        return $progress >= 1 ? 'echue' : ($progress >= 0.8 ? 'proche' : 'ok');
    }

    private function findTechnician(MaintenanceSchedule $schedule): ?User
    {
        $site = $schedule->getMachine()?->getSite();
        if ($site === null) {
            return null;
        }

        $technicians = $this->userRepository->findBy(['site' => $site]);
        $technicians = array_values(array_filter($technicians, static fn (User $user): bool => in_array('ROLE_TECHNICIEN', $user->getRoles(), true)));
        usort($technicians, fn (User $a, User $b): int => $this->interventionRepository->countOpenForTechnician($a) <=> $this->interventionRepository->countOpenForTechnician($b));

        return $technicians[0] ?? null;
    }

    /** @return list<User> */
    private function responsablesFor(MaintenanceSchedule $schedule): array
    {
        $site = $schedule->getMachine()?->getSite();
        if ($site === null) {
            return [];
        }

        return array_values(array_filter(
            $this->userRepository->findBy(['site' => $site]),
            static fn (User $user): bool => in_array('ROLE_RESPONSABLE', $user->getRoles(), true)
        ));
    }

    private function addNotification(User $recipient, string $message, string $type, ?\App\Entity\Fault $fault, Intervention $intervention): void
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
