<?php

namespace App\Controller\Admin;

use App\Entity\Site;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

final class PlanningController extends AbstractController
{
    #[Route('/admin/maintenance-plan', name: 'admin_maintenance_plan')]
    #[Route('/maintenance-plan', name: 'maintenance_plan')]
    public function index(Request $request, MaintenanceScheduleRepository $scheduleRepository, SiteRepository $siteRepository): Response
    {
        $siteId = $request->query->getInt('site');
        $user = $this->getUser();
        $isAdmin = $user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true);
        $userSite = $user instanceof User ? $user->getSite() : null;
        if (!$isAdmin && $userSite !== null) {
            $siteId = $userSite->getId() ?? 0;
        }
        $schedules = $scheduleRepository->findForPlanning($siteId > 0 ? $siteId : null);

        return $this->render($isAdmin ? 'admin/maintenance_plan.html.twig' : 'maintenance/plan.html.twig', [
            'schedules' => $schedules,
            'sites' => $isAdmin ? $siteRepository->findBy([], ['name' => 'ASC']) : ($userSite !== null ? [$userSite] : []),
            'selectedSite' => $siteId,
        ]);
    }
}
