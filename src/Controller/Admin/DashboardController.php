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
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
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
            ->setTitle('RolTech GMAO')

            // dark by default, matching the login page's Nord palette
            ->setDefaultColorScheme('dark')

            ->setTheme(Theme::new()
                // Nord "frost" blue — same family as the login page's accent colors
                ->primaryColor('#5e81ac', dark: '#88c0d0')
                ->radius('lg')
                // 'lg' bumps paddings/control sizes across the whole interface —
                // this is what makes icons, buttons, and rows feel bigger/easier to hit
                ->spacing('lg')
                // 'slate' is the coolest built-in gray scale, closest to Nord's
                // polar-night/snow-storm neutrals
                ->grays('slate', dark: 'slate')
            );
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-house');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::linkTo(MachineCrudController::class, 'Machines', 'fa fa-gears');
        yield MenuItem::linkTo(FaultCrudController::class, 'Faults', 'fa fa-triangle-exclamation');
        yield MenuItem::linkTo(InterventionCrudController::class, 'Interventions', 'fa fa-wrench');
    }
}
