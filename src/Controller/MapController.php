<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SiteMapDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * /carte — Tunisia map (technicien and above).
 * Scoping is enforced by SiteMapDataProvider, not in the template (rule 26).
 * The page uses data-turbo="false" on the map container to prevent Turbo from
 * re-initialising Leaflet on subsequent navigations (PROJECT_HANDOFF.md note 4).
 */
#[IsGranted('ROLE_TECHNICIEN')]
final class MapController extends AbstractController
{
    public function __construct(private readonly SiteMapDataProvider $mapDataProvider) {}

    #[Route('/carte', name: 'map_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $sites = $this->mapDataProvider->forUser($user);

        return $this->render('map/index.html.twig', [
            'sitesJson' => json_encode($sites, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    #[Route('/carte/card', name: 'map_card', methods: ['GET'])]
    public function card(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $sites = $this->mapDataProvider->forUser($user);

        return $this->render('map/_card.html.twig', [
            'sitesJson' => json_encode($sites, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
