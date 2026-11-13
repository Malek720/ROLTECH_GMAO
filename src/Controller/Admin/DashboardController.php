<?php

namespace App\Controller\Admin;

use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\MachineCrudController;
use App\Controller\Admin\FaultCrudController;
use App\Controller\Admin\InterventionCrudController;
use App\Controller\Admin\SiteCrudController;
use App\Controller\Admin\MachineModuleCrudController;
use App\Controller\Admin\PartCrudController;
use App\Controller\Admin\MaintenanceTaskCrudController;
use App\Controller\Admin\MaintenanceScheduleCrudController;
use App\Controller\Admin\NotificationCrudController;
use App\Controller\Admin\MachineModelCrudController;
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
        private \App\Service\AdminStatsProvider $statsProvider,
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
            'statsJson' => json_encode($this->statsProvider->getStats()),
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
                ->radius('xl')
                // 'xl' is the largest preset — biggest paddings, control sizes,
                // row heights across the whole interface
                ->spacing('xl')
                // 'slate' is the coolest built-in gray scale, closest to Nord's
                // polar-night/snow-storm neutrals
                ->grays('slate', dark: 'slate')
            );
    }

    public function configureAssets(): \EasyCorp\Bundle\EasyAdminBundle\Config\Assets
    {
        // addCssFile (unlike addAssetMapperEntry) is the plain-CSS method —
        // it doesn't go through importmap.php, it resolves the path via the
        // Asset component directly, same mechanism as asset() on /login.
        return \EasyCorp\Bundle\EasyAdminBundle\Config\Assets::new()
            ->addCssFile('admin-overrides.css')
            ->addJsFile('admin-overrides.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-house');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::linkTo(MachineCrudController::class, 'Machines', 'fa fa-gears');
        yield MenuItem::linkTo(FaultCrudController::class, 'Faults', 'fa fa-triangle-exclamation');
        yield MenuItem::linkTo(InterventionCrudController::class, 'Interventions', 'fa fa-wrench');
        yield MenuItem::linkTo(SiteCrudController::class, 'Sites', 'fa fa-location-dot');
        yield MenuItem::linkTo(MachineModelCrudController::class, 'Modèles', 'fa fa-boxes-stacked');
        yield MenuItem::linkTo(MachineModuleCrudController::class, 'Modules', 'fa fa-sitemap');
        yield MenuItem::linkTo(PartCrudController::class, 'Pièces', 'fa fa-cubes');
        yield MenuItem::linkTo(MaintenanceTaskCrudController::class, 'Tâches de maintenance', 'fa fa-list-check');
        yield MenuItem::linkTo(MaintenanceScheduleCrudController::class, 'Plannings', 'fa fa-calendar-check');
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');
        yield MenuItem::linkToRoute('Plan de maintenance', 'fa fa-calendar-days', 'admin_maintenance_plan');
    }
}