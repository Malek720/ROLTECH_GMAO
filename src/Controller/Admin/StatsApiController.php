<?php

namespace App\Controller\Admin;

use App\Service\AdminStatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class StatsApiController extends AbstractController
{
    #[Route('/admin/stats-data', name: 'admin_stats_data')]
    public function data(AdminStatsProvider $statsProvider): JsonResponse
    {
        return new JsonResponse($statsProvider->getStats());
    }
}