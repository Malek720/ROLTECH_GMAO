<?php

namespace App\Controller\Admin;

use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\MachineCrudController;
use App\Controller\Admin\FaultCrudController;
use App\Controller\Admin\InterventionCrudController;
use App\Repository\MachineRepository;
use App\Repository\FaultRepository;
use App\Repository\InterventionRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private MachineRepository $machineRepository,
        private FaultRepository $faultRepository,
        private InterventionRepository $interventionRepository,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'totalMachines' => $this->machineRepository->count([]),
            'machinesEnPanne' => $this->machineRepository->count(['status' => 'en panne']),
            'machinesEnMaintenance' => $this->machineRepository->count(['status' => 'en maintenance']),
            'machinesEnService' => $this->machineRepository->count(['status' => 'en service']),
            'faultsOuvertes' => $this->faultRepository->count(['status' => 'ouverte']),
            'interventionsEnCours' => $this->interventionRepository->count(['status' => 'en cours']),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Roltech Gmao');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::linkTo(MachineCrudController::class, 'Machines', 'fa fa-cogs');
        yield MenuItem::linkTo(FaultCrudController::class, 'Faults', 'fa fa-exclamation-triangle');
        yield MenuItem::linkTo(InterventionCrudController::class, 'Interventions', 'fa fa-wrench');
    }
}