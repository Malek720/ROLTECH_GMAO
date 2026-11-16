<?php

namespace App\Controller;

use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Repository\MachineModuleRepository;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_RESPONSABLE')]
final class MachineFicheController extends AbstractController
{
    #[Route('/machines/{id}/fiche', name: 'machine_fiche', methods: ['GET', 'POST'])]
    public function fiche(
        Request $request,
        Machine $machine,
        MachineModuleRepository $moduleRepository,
        EntityManagerInterface $entityManager,
        QrCodeService $qrCodeService,
    ): Response
    {
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('machine_edit_'.$machine->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $serialNumber = trim((string) $request->request->get('serial_number', ''));
            $status = (string) $request->request->get('status', '');
            if ($serialNumber === '') {
                $errors['serial_number'] = 'Le numéro de série est obligatoire.';
            } elseif (($existing = $entityManager->getRepository(Machine::class)->findOneBy(['serialNumber' => $serialNumber])) instanceof Machine && $existing !== $machine) {
                $errors['serial_number'] = 'Ce numéro de série est déjà utilisé.';
            }
            if (!in_array($status, ['en service', 'en panne', 'en maintenance'], true)) {
                $errors['status'] = 'Sélectionnez un statut valide.';
            }
            if ($errors === []) {
                $machine
                    ->setSerialNumber($serialNumber)
                    ->setLocation(trim((string) $request->request->get('location', '')) ?: null)
                    ->setStatus($status);
                $entityManager->flush();
                $this->addFlash('success', 'Fiche machine mise à jour.');

                return $this->redirectToRoute('machine_fiche', ['id' => $machine->getId()]);
            }
        }

        $selectedModuleId = $request->query->getInt('module', 0);
        $selectedModule = null;
        if ($selectedModuleId > 0 && $machine->getMachineModel() !== null) {
            $candidate = $moduleRepository->find($selectedModuleId);
            if ($candidate instanceof MachineModule && (int) $candidate->getMachineModel()?->getId() === (int) $machine->getMachineModel()->getId()) {
                $selectedModule = $candidate;
            }
        }

        $modules = $machine->getMachineModel() === null ? [] : $moduleRepository->findForModel($machine->getMachineModel());
        $timeline = [];
        foreach ($machine->getFaults() as $fault) {
            if ($selectedModule !== null) {
                $faultModuleId = $fault->getModule()?->getId() ?? $fault->getPart()?->getModule()?->getId();
                if ($faultModuleId !== $selectedModule->getId()) {
                    continue;
                }
            }
            $timeline[] = ['date' => $fault->getReportedAt(), 'kind' => 'Panne', 'label' => $fault->getDescription(), 'status' => $fault->getStatus(), 'fault' => $fault];
            foreach ($fault->getInterventions() as $intervention) {
                $timeline[] = ['date' => $intervention->getEndDate() ?? $intervention->getStartDate(), 'kind' => 'Intervention', 'label' => (string) $intervention, 'status' => $intervention->getStatus(), 'intervention' => $intervention];
            }
        }
        foreach ($machine->getMaintenanceSchedules() as $schedule) {
            if ($schedule->getArchivedAt() !== null) {
                continue;
            }
            if ($selectedModule !== null) {
                $task = $schedule->getTask();
                $schedModuleId = $task?->getModule()?->getId() ?? $task?->getPart()?->getModule()?->getId();
                if ($schedModuleId !== $selectedModule->getId()) {
                    continue;
                }
            }
            foreach ($schedule->getInterventions() as $intervention) {
                $timeline[] = ['date' => $intervention->getEndDate() ?? $intervention->getStartDate(), 'kind' => 'Tâche terminée', 'label' => (string) $schedule->getTask(), 'status' => $intervention->getStatus(), 'intervention' => $intervention];
            }
        }
        usort($timeline, static fn (array $left, array $right): int => ($right['date']?->getTimestamp() ?? 0) <=> ($left['date']?->getTimestamp() ?? 0));

        $response = $this->render('machine/fiche.html.twig', [
            'machine' => $machine,
            'modules' => $modules,
            'selectedModule' => $selectedModule,
            'openFaults' => $machine->getFaults()->filter(static fn ($fault): bool => $fault->getStatus() !== 'resolue'),
            'upcomingSchedules' => $machine->getMaintenanceSchedules()->filter(static fn ($schedule): bool => $schedule->getArchivedAt() === null),
            'maintenanceHistory' => $machine->getMaintenanceSchedules()->filter(static fn ($schedule): bool => $schedule->getArchivedAt() === null && $schedule->getInterventions()->exists(static fn ($key, $intervention): bool => $intervention->getStatus() === 'terminee')),
            'timeline' => $timeline,
            'errors' => $errors,
            'qrUrl' => $qrCodeService->faultDeclarationUrl((int) $machine->getId()),
            'qrSvg' => $qrCodeService->faultDeclarationSvg((int) $machine->getId()),
        ]);
        if ($request->isMethod('POST') && $errors !== []) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }
}
