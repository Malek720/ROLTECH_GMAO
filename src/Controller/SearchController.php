<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Repository\MachineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SearchController extends AbstractController
{
    private const MIN_LENGTH = 2;

    #[Route('/recherche', name: 'global_search', methods: ['GET'])]
    public function index(
        Request $request,
        MachineRepository $machineRepository,
        InterventionRepository $interventionRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $term = trim((string) $request->query->get('q', ''));
        $tooShort = $term !== '' && mb_strlen($term) < self::MIN_LENGTH;

        // Below the minimum length no query runs at all, so the same hint is shown
        // whatever the database contains. Scoping itself lives in the repositories.
        $machines = [];
        $interventions = [];
        if ($term !== '' && !$tooShort) {
            $machines = $machineRepository->search($user, $term);
            $interventions = $interventionRepository->searchById($user, $term);
        }

        return $this->render('search/results.html.twig', [
            'term' => $term,
            'tooShort' => $tooShort,
            'machines' => $machines,
            'interventions' => $interventions,
        ]);
    }
}
