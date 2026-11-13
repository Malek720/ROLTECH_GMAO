<?php

namespace App\Controller;

use App\Entity\Fault;
use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Entity\Part;
use App\Entity\User;
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
    public function new(Request $request, EntityManagerInterface $entityManager, FaultWorkflow $workflow): Response
    {
        $machines = $entityManager->getRepository(Machine::class)->findBy([], ['name' => 'ASC']);
        $symptoms = $workflow->troubleshooting();
        $data = $request->request->all('fault');
        $machineId = (int) ($data['machine'] ?? $request->query->get('machine', 0));
        $selectedMachine = $machineId > 0 ? $entityManager->getRepository(Machine::class)->find($machineId) : null;
        $modules = $selectedMachine instanceof Machine && $selectedMachine->getMachineModel() !== null
            ? $entityManager->getRepository(MachineModule::class)->findForModel($selectedMachine->getMachineModel())
            : [];
        $parts = [];
        foreach ($modules as $catalogModule) {
            foreach ($catalogModule->getParts() as $catalogPart) {
                $parts[] = $catalogPart;
            }
        }

        if ($request->isMethod('POST')) {
            $machine = $entityManager->getRepository(Machine::class)->find((int) ($data['machine'] ?? 0));
            $moduleId = (string) ($data['module'] ?? '');
            $partId = (string) ($data['part'] ?? '');
            $module = $moduleId !== '' ? $entityManager->getRepository(MachineModule::class)->find((int) $moduleId) : null;
            $part = $partId !== '' ? $entityManager->getRepository(Part::class)->find((int) $partId) : null;
            if (!$machine instanceof Machine || ($moduleId !== '' && !$module instanceof MachineModule) || ($partId !== '' && !$part instanceof Part)) {
                $this->addFlash('error', 'Machine, module ou pièce invalide.');
            } elseif (!$this->isCsrfTokenValid('fault_declare', $data['_token'] ?? '')) {
                $this->addFlash('error', 'Le formulaire a expiré. Veuillez réessayer.');
            } else {
                try {
                    $workflow->declare(
                        $this->getUserOrFail(),
                        $machine,
                        $module,
                        $part,
                        (string) ($data['symptom'] ?? 'autre'),
                        trim((string) ($data['description'] ?? '')),
                        (string) ($data['severity'] ?? ''),
                    );
                    $this->addFlash('success', 'La panne a été déclarée et l’intervention a été créée.');

                    return $this->redirectToRoute('fault_new');
                } catch (\InvalidArgumentException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('fault/new.html.twig', compact('machines', 'modules', 'parts', 'symptoms', 'data'));
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
