<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RoleDashboardController extends AbstractController
{
    #[Route('/technicien', name: 'technicien_dashboard')]
    public function technicien(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'roleLabel' => 'Technicien',
        ]);
    }

    #[Route('/responsable', name: 'responsable_dashboard')]
    public function responsable(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'roleLabel' => 'Responsable',
        ]);
    }
}
