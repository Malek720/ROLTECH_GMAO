<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Repository\MachineModuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MachineFicheController extends AbstractController
{
    #[Route('/machines/{id}/fiche', name: 'machine_fiche')]
    public function fiche(Machine $machine, MachineModuleRepository $moduleRepository): Response
    {
        return $this->render('machine/fiche.html.twig', [
            'machine' => $machine,
            'modules' => $machine->getMachineModel() === null ? [] : $moduleRepository->findForModel($machine->getMachineModel()),
            'openFaults' => $machine->getFaults()->filter(static fn ($fault): bool => $fault->getStatus() !== 'resolue'),
            'upcomingSchedules' => $machine->getMaintenanceSchedules()->filter(static fn ($schedule): bool => $schedule->getArchivedAt() === null && $schedule->getState() !== 'ok'),
            'maintenanceHistory' => $machine->getMaintenanceSchedules()->filter(
                static fn ($schedule): bool => $schedule->getArchivedAt() === null && $schedule->getInterventions()->exists(
                    static fn ($key, $intervention): bool => $intervention->getStatus() === 'terminee'
                )
            ),
        ]);
    }
}
