<?php

namespace App\Controller;

use App\Entity\Site;
use App\Entity\User;
use App\Repository\MachineRepository;
use App\Service\SiteMapDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * /sites/{id} — Site detail page with machines grouped by zone.
 *
 * Access rule (rule 26):
 *   - Admin / responsable: any site.
 *   - Technicien: only their own site → 403 otherwise.
 *
 * The route path /sites/{id} does not conflict with /machines/{id}/fiche
 * and is reachable by ROLE_TECHNICIEN (see security.yaml access_control).
 */
#[IsGranted('ROLE_TECHNICIEN')]
final class SiteController extends AbstractController
{
    public function __construct(
        private readonly MachineRepository   $machineRepository,
        private readonly SiteMapDataProvider $mapDataProvider,
    ) {}

    #[Route('/sites/{id}', name: 'site_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Site $site): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Object-level auth: a technicien can only see their own site (rule 26, 38).
        if (!$this->mapDataProvider->canViewSite($user, $site)) {
            throw $this->createAccessDeniedException('Accès refusé à ce site.');
        }

        // Machines grouped by zone (Machine.location).
        $machines = $this->machineRepository->findForSite($site);
        $byZone = [];
        foreach ($machines as $machine) {
            $zone = $machine->getLocation() ?? 'Zone non renseignée';
            $byZone[$zone][] = $machine;
        }
        ksort($byZone);

        return $this->render('site/show.html.twig', [
            'site'   => $site,
            'byZone' => $byZone,
        ]);
    }
}
