<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FaultRepository;
use App\Repository\InterventionRepository;
use App\Repository\MachineRepository;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RoleDashboardController extends AbstractController
{
    public function __construct(
        private InterventionRepository $interventionRepository,
        private NotificationRepository $notificationRepository,
        private FaultRepository $faultRepository,
        private MaintenanceScheduleRepository $scheduleRepository,
        private MachineRepository $machineRepository,
    ) {
    }

    #[Route('/technicien', name: 'technicien_dashboard')]
    public function technicien(): Response
    {
        $user = $this->getAuthenticatedUser();

        return $this->render('dashboard/technicien.html.twig', [
            'interventions' => $this->interventionRepository->findForTechnician($user),
            'notifications' => $this->notificationRepository->findForUser($user),
        ]);
    }

    #[Route('/responsable', name: 'responsable_dashboard')]
    public function responsable(): Response
    {
        $user = $this->getAuthenticatedUser();
        $site = $user->getSite();
        $machines = $this->machineRepository->findForSite($site);
        $machineCounts = [];
        foreach ($machines as $machine) {
            $status = $machine->getStatus() ?? 'Non défini';
            $machineCounts[$status] = ($machineCounts[$status] ?? 0) + 1;
        }

        return $this->render('dashboard/responsable.html.twig', [
            'faultsToDispatch' => $this->faultRepository->findToDispatch($site),
            'overdueSchedules' => $this->scheduleRepository->findOverdueForSite($site),
            'machines' => $machines,
            'machineCounts' => $machineCounts,
            'site' => $site,
        ]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
