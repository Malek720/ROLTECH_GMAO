<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Entity\User;
use App\Repository\MachineRepository;
use App\Service\ModuleHealthProvider;
use App\Service\SiteMapDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Read-only viewer data endpoint for 3D schematic & fallback module view.
 * Reachable by ROLE_TECHNICIEN and above under /parc prefix.
 *
 * Scoping:
 *  - Admin / responsable: all machines.
 *  - Technicien: only machines belonging to their own site (403 otherwise).
 */
#[IsGranted('ROLE_TECHNICIEN')]
final class MachineViewerController extends AbstractController
{
    #[Route('/parc/{id}/viewer-data', name: 'parc_machine_viewer_data', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function viewerData(
        int $id,
        MachineRepository $machineRepository,
        ModuleHealthProvider $healthProvider,
        SiteMapDataProvider $mapDataProvider,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $machine = $machineRepository->find($id);
        if (!$machine instanceof Machine) {
            throw $this->createNotFoundException('Machine introuvable.');
        }

        $site = $machine->getSite();
        $isAllowed = in_array('ROLE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_RESPONSABLE', $user->getRoles(), true)
            || ($site !== null && $mapDataProvider->canViewSite($user, $site));

        if (!$isAllowed) {
            throw $this->createAccessDeniedException('Accès refusé à cette machine.');
        }

        $data = $healthProvider->buildViewerData($machine);

        return $this->json($data, Response::HTTP_OK);
    }
}
