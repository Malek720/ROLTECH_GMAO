<?php

namespace App\Controller;

use App\Entity\Intervention;
use App\Entity\InterventionPart;
use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Service\InterventionWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/interventions')]
final class InterventionController extends AbstractController
{
    #[Route('', name: 'interventions_mine', methods: ['GET'])]
    public function index(Request $request, InterventionRepository $repository): Response
    {
        $user = $this->technician();
        $status = (string) $request->query->get('status', '');
        if ($status !== '' && !in_array($status, ['planifiee', 'en cours', 'terminee'], true)) {
            $status = '';
        }

        return $this->render('intervention/index.html.twig', [
            'interventions' => $repository->findForTechnician($user, $status !== '' ? $status : null),
            'selectedStatus' => $status,
        ]);
    }

    #[Route('/{id}', name: 'intervention_show', methods: ['GET'])]
    public function show(Intervention $intervention, InterventionWorkflow $workflow): Response
    {
        $this->assertCanView($intervention);

        return $this->render('intervention/show.html.twig', $this->viewData($intervention, $workflow));
    }

    #[Route('/{id}/start', name: 'intervention_start', methods: ['POST'])]
    public function start(Request $request, Intervention $intervention, InterventionWorkflow $workflow): Response
    {
        $this->assertOwner($intervention);
        if (!$this->isCsrfTokenValid('intervention_start_'.$intervention->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $workflow->start($intervention, $this->technician(), (array) $request->request->all('lockout'));
            $this->addFlash('success', 'Intervention démarrée.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('intervention_show', ['id' => $intervention->getId()]);
    }

    #[Route('/{id}/checklist', name: 'intervention_checklist', methods: ['POST'])]
    public function checklist(Request $request, Intervention $intervention, InterventionWorkflow $workflow): Response
    {
        $this->assertOwner($intervention);
        if (!$this->isCsrfTokenValid('intervention_checklist_'.$intervention->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $workflow->updateChecklist($intervention, $this->technician(), (array) $request->request->all('steps'));
            $this->addFlash('success', 'Progression de la procédure enregistrée.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('intervention_show', ['id' => $intervention->getId()]);
    }

    #[Route('/{id}/complete', name: 'intervention_complete', methods: ['POST'])]
    public function complete(Request $request, Intervention $intervention, InterventionWorkflow $workflow): Response
    {
        $this->assertOwner($intervention);
        if (!$this->isCsrfTokenValid('intervention_complete_'.$intervention->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $workflow->complete(
                $intervention,
                $this->technician(),
                (string) $request->request->get('comment', ''),
                (array) $request->request->all('parts')
            );
            $this->addFlash('success', 'Intervention terminée.');
            return $this->redirectToRoute('intervention_show', ['id' => $intervention->getId()]);
        } catch (\InvalidArgumentException $exception) {
            $response = $this->render('intervention/show.html.twig', $this->viewData($intervention, $workflow, $exception->getMessage()));
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            return $response;
        }
    }

    #[Route('/{id}/cannot-intervene', name: 'intervention_cannot', methods: ['POST'])]
    public function cannotIntervene(Request $request, Intervention $intervention, InterventionWorkflow $workflow): Response
    {
        $this->assertOwner($intervention);
        if (!$this->isCsrfTokenValid('intervention_cannot_'.$intervention->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $workflow->cannotIntervene($intervention, $this->technician(), (string) $request->request->get('reason', ''));
            $this->addFlash('warning', 'Intervention renvoyée au dispatch.');
            return $this->redirectToRoute('interventions_mine');
        } catch (\InvalidArgumentException $exception) {
            $response = $this->render('intervention/show.html.twig', $this->viewData($intervention, $workflow, $exception->getMessage()));
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            return $response;
        }
    }

    /** @return array<string, mixed> */
    private function viewData(Intervention $intervention, InterventionWorkflow $workflow, ?string $error = null): array
    {
        $machine = $intervention->getFault()?->getMachine() ?? $intervention->getSchedule()?->getMachine();
        $parts = [];
        foreach ($machine?->getMachineModel()?->getModules() ?? [] as $module) {
            foreach ($module->getParts() as $part) {
                $parts[] = $part;
            }
        }

        return [
            'intervention' => $intervention,
            'machine' => $machine,
            'procedureSteps' => $workflow->procedureSteps($intervention),
            'lockoutItems' => InterventionWorkflow::LOCKOUT_ITEMS,
            'availableParts' => $parts,
            'error' => $error,
        ];
    }

    private function technician(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !in_array('ROLE_TECHNICIEN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertOwner(Intervention $intervention): void
    {
        if ($intervention->getTechnician() !== $this->technician()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertCanView(Intervention $intervention): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (in_array('ROLE_TECHNICIEN', $user->getRoles(), true) && $intervention->getTechnician() !== $user) {
            throw $this->createAccessDeniedException();
        }
        if (
            !in_array('ROLE_TECHNICIEN', $user->getRoles(), true)
            && !in_array('ROLE_RESPONSABLE', $user->getRoles(), true)
            && !in_array('ROLE_ADMIN', $user->getRoles(), true)
        ) {
            throw $this->createAccessDeniedException();
        }
    }
}
