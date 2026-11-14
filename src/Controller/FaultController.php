<?php

namespace App\Controller;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Entity\Part;
use App\Entity\User;
use App\Repository\FaultRepository;
use App\Repository\MachineRepository;
use App\Service\FaultWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/faults')]
final class FaultController extends AbstractController
{
    #[Route('/new', name: 'fault_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        MachineRepository $machineRepository,
        FaultWorkflow $workflow,
    ): Response
    {
        $user = $this->getUserOrFail();
        $machines = $machineRepository->findVisibleForFaultDeclaration($user);
        $symptoms = $workflow->troubleshooting();
        $data = $request->request->all('fault');
        $machineId = (int) ($data['machine'] ?? $request->query->get('machine', 0));
        $data['machine'] = $data['machine'] ?? ($machineId > 0 ? $machineId : '');
        $submissionToken = trim((string) ($data['_submission_token'] ?? ''));
        if ($submissionToken === '') {
            $submissionToken = bin2hex(random_bytes(16));
        }
        $data['_submission_token'] = $submissionToken;
        $errors = [];
        $machineById = [];
        $modelsById = [];
        foreach ($machines as $machine) {
            $machineById[$machine->getId()] = $machine;
            if ($machine->getMachineModel() !== null) {
                $modelsById[$machine->getMachineModel()->getId()] = $machine->getMachineModel();
            }
        }
        $selectedMachine = $machineById[$machineId] ?? null;
        if ($request->isMethod('GET') && $machineId > 0 && !$selectedMachine instanceof Machine) {
            $errors['machine'] = 'Cette machine n’est pas accessible avec votre compte.';
            $data['machine'] = '';
        }
        $machineGroups = [];
        foreach ($machines as $machine) {
            $siteId = $machine->getSite()?->getId() ?? 0;
            $machineGroups[$siteId]['site'] = $machine->getSite();
            $machineGroups[$siteId]['machines'][] = $machine;
        }
        $catalogModules = [];
        foreach ($machines as $machine) {
            $model = $machine->getMachineModel();
            if ($model === null || isset($catalogModules[$model->getId()])) {
                continue;
            }

            $catalogModules[$model->getId()] = $entityManager
                ->getRepository(MachineModule::class)
                ->findForModel($model);
        }
        $allModules = array_merge(...array_values($catalogModules ?: [[]]));
        $allParts = [];
        foreach ($allModules as $catalogModule) {
            foreach ($catalogModule->getParts() as $catalogPart) {
                $allParts[] = $catalogPart;
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('fault_declare', $data['_token'] ?? '')) {
                $errors['_token'] = 'Le formulaire a expiré. Veuillez réessayer.';
            }
            if (!preg_match('/^[a-f0-9]{32,64}$/', $submissionToken)) {
                $errors['_submission_token'] = 'Le formulaire est invalide. Veuillez réessayer.';
            }
            $machine = $machineById[(int) ($data['machine'] ?? 0)] ?? null;
            $moduleId = (string) ($data['module'] ?? '');
            $partId = (string) ($data['part'] ?? '');
            $module = $moduleId !== '' ? $entityManager->getRepository(MachineModule::class)->find((int) $moduleId) : null;
            $part = $partId !== '' ? $entityManager->getRepository(Part::class)->find((int) $partId) : null;
            if (!$machine instanceof Machine) {
                $errors['machine'] = 'Sélectionnez une machine accessible.';
            }
            if ($moduleId !== '' && !$module instanceof MachineModule) {
                $errors['module'] = 'Le module sélectionné est invalide.';
            }
            if ($partId !== '' && !$part instanceof Part) {
                $errors['part'] = 'La pièce sélectionnée est invalide.';
            }
            if (
                !array_key_exists('symptom', $data)
                || ($data['symptom'] ?? '') === ''
                || (($data['symptom'] ?? '') !== 'autre' && !array_key_exists((string) $data['symptom'], $symptoms))
            ) {
                $errors['symptom'] = 'Sélectionnez un symptôme.';
            }
            if (!in_array($data['severity'] ?? '', ['critique', 'moyenne', 'faible'], true)) {
                $errors['severity'] = 'Sélectionnez une gravité valide.';
            }
            if (trim((string) ($data['description'] ?? '')) === '') {
                $errors['description'] = 'La description est obligatoire.';
            }
            if ($errors === []) {
                try {
                    $fault = $workflow->declare(
                        $user,
                        $machine,
                        $module,
                        $part,
                        (string) ($data['symptom'] ?? 'autre'),
                        trim((string) ($data['description'] ?? '')),
                        (string) ($data['severity'] ?? ''),
                        $submissionToken,
                    );
                    $this->addFlash('success', 'La panne a été déclarée et l’intervention a été créée.');

                    return $this->redirectToRoute('fault_confirmation', ['id' => $fault->getId()]);
                } catch (\InvalidArgumentException $exception) {
                    $message = $exception->getMessage();
                    $field = str_contains($message, 'module') ? 'module' : (str_contains($message, 'pièce') ? 'part' : 'form');
                    $errors[$field] = $message;
                }
            }
        }

        $response = $this->render('fault/new.html.twig', compact('machineGroups', 'modelsById', 'selectedMachine', 'allModules', 'allParts', 'symptoms', 'data', 'errors'));
        if ($request->isMethod('POST')) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    #[Route('', name: 'faults_mine', methods: ['GET'])]
    public function mine(FaultRepository $faultRepository): Response
    {
        return $this->render('fault/mine.html.twig', [
            'faults' => $faultRepository->findForReporter($this->getUserOrFail()),
        ]);
    }

    #[Route('/{id}/confirmation', name: 'fault_confirmation', methods: ['GET'])]
    public function confirmation(Fault $fault): Response
    {
        if ($fault->getReportedBy() !== $this->getUserOrFail()) {
            throw $this->createAccessDeniedException();
        }

        $intervention = $fault->getInterventions()->first();

        return $this->render('fault/confirmation.html.twig', [
            'fault' => $fault,
            'intervention' => $intervention instanceof \App\Entity\Intervention ? $intervention : null,
        ]);
    }

    #[Route('/interventions/{id}', name: 'fault_intervention', methods: ['GET'])]
    public function intervention(Intervention $intervention): Response
    {
        $fault = $intervention->getFault();
        $user = $this->getUserOrFail();
        if (
            !$fault instanceof Fault
            || (
                in_array('ROLE_TECHNICIEN', $user->getRoles(), true)
                && $intervention->getTechnician() !== $user
            )
            || (
                !in_array('ROLE_TECHNICIEN', $user->getRoles(), true)
                && $fault->getReportedBy() !== $user
            )
        ) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('fault/intervention.html.twig', ['intervention' => $intervention]);
    }

    private function getUserOrFail(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
