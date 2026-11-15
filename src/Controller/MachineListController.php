<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MachineModelRepository;
use App\Repository\MachineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * /parc — Machine list with search and filters.
 *
 * Uses /parc instead of /machines to avoid any collision with /machines/{id}/fiche
 * (rule from AGENTS.md note 1 on security.yaml ordering).
 * Reachable by ROLE_TECHNICIEN and above; scoping is enforced by
 * MachineRepository::findForList(), never in the template (rule 26).
 */
#[IsGranted('ROLE_TECHNICIEN')]
#[Route('/parc', name: 'machine_list', methods: ['GET'])]
final class MachineListController extends AbstractController
{
    public function __construct(
        private readonly MachineRepository      $machineRepository,
        private readonly MachineModelRepository $modelRepository,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $filters = [
            'q'      => $request->query->getString('q'),
            'status' => $request->query->getString('status'),
            'model'  => $request->query->getString('model'),
            'family' => $request->query->getString('family'),
        ];

        $machines = $this->machineRepository->findForList($user, $filters);

        // Build filter option lists (all models/families visible to this user).
        $allMachines = $this->machineRepository->findForList($user);
        $families = [];
        $models   = [];
        foreach ($allMachines as $m) {
            if ($m->getMachineModel() === null) {
                continue;
            }
            $f = $m->getMachineModel()->getFamily();
            if ($f !== null && !in_array($f, $families, true)) {
                $families[] = $f;
            }
            $mid = $m->getMachineModel()->getId();
            if ($mid !== null && !isset($models[$mid])) {
                $models[$mid] = $m->getMachineModel();
            }
        }
        sort($families);

        return $this->render('machine/index.html.twig', [
            'machines' => $machines,
            'filters'  => $filters,
            'families' => $families,
            'models'   => array_values($models),
        ]);
    }
}
