<?php

namespace App\Controller;

use App\Entity\Fault;
use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Part;
use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Repository\MachineModuleRepository;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\MaintenanceTaskRepository;
use App\Repository\PartRepository;
use App\Repository\UserRepository;
use App\Service\ResponsableWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/responsable')]
#[IsGranted('ROLE_RESPONSABLE')]
final class ResponsableController extends AbstractController
{
    #[Route('/dispatch', name: 'responsable_dispatch', methods: ['GET'])]
    public function dispatch(InterventionRepository $interventionRepository, ResponsableWorkflow $workflow): Response
    {
        $interventions = $interventionRepository->findForDispatch();

        return $this->render('responsable/dispatch.html.twig', [
            'interventions' => $interventions,
            'techniciansByIntervention' => array_reduce(
                $interventions,
                static fn (array $carry, $intervention): array => $carry + [$intervention->getId() => $workflow->techniciansFor($intervention)],
                []
            ),
        ]);
    }

    #[Route('/dispatch/{id}/assign', name: 'responsable_dispatch_assign', methods: ['POST'])]
    public function assign(Request $request, \App\Entity\Intervention $intervention, UserRepository $userRepository, ResponsableWorkflow $workflow): Response
    {
        $responsable = $this->responsable();
        if (!$this->isCsrfTokenValid('responsable_assign_'.$intervention->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $technician = $userRepository->find((int) $request->request->get('technician'));
        if (!$technician instanceof User) {
            $this->addFlash('error', 'Sélectionnez un technicien.');
        } else {
            try {
                $workflow->assign($intervention, $responsable, $technician);
                $this->addFlash('success', 'Intervention affectée.');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('responsable_dispatch');
    }

    #[Route('/machines/{id}/interventions/new', name: 'responsable_manual_intervention', methods: ['GET', 'POST'])]
    public function manualIntervention(
        Request $request,
        Machine $machine,
        MachineModuleRepository $moduleRepository,
        MaintenanceTaskRepository $taskRepository,
        PartRepository $partRepository,
        UserRepository $userRepository,
        ResponsableWorkflow $workflow,
    ): Response {
        $responsable = $this->responsable();
        $data = $request->request->all('intervention');
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('responsable_manual_intervention_'.$machine->getId(), (string) ($data['_token'] ?? ''))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $module = $this->entityOrNull($moduleRepository, $data['module'] ?? null, MachineModule::class);
            $part = $this->entityOrNull($partRepository, $data['part'] ?? null, Part::class);
            $task = $this->entityOrNull($taskRepository, $data['task'] ?? null, MaintenanceTask::class);
            $technician = $this->entityOrNull($userRepository, $data['technician'] ?? null, User::class);
            try {
                $intervention = $workflow->createManual(
                    $responsable,
                    $machine,
                    (string) ($data['type'] ?? ''),
                    $task,
                    $module,
                    $part,
                    $technician,
                    (string) ($data['description'] ?? ''),
                    (string) ($data['severity'] ?? '')
                );
                $this->addFlash('success', 'Intervention créée.');

                return $this->redirectToRoute('intervention_show', ['id' => $intervention->getId()]);
            } catch (\InvalidArgumentException $exception) {
                $errors['form'] = $exception->getMessage();
            }
        }

        $response = $this->render('responsable/manual_intervention.html.twig', [
            'machine' => $machine,
            'modules' => $moduleRepository->findForModel($machine->getMachineModel()),
            'tasks' => $taskRepository->findForModel($machine->getMachineModel()),
            'technicians' => $machine->getSite() ? $userRepository->findTechniciansForSite($machine->getSite()) : [],
            'data' => $data,
            'errors' => $errors,
        ]);
        if ($request->isMethod('POST')) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    #[Route('/faults/{id}/close', name: 'responsable_fault_close', methods: ['POST'])]
    public function closeFault(Request $request, Fault $fault, ResponsableWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid('responsable_fault_close_'.$fault->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $workflow->closeFault($fault, $this->responsable());
            $this->addFlash('success', 'Panne clôturée.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('machine_fiche', ['id' => $fault->getMachine()->getId()]);
    }

    #[Route('/faults/{id}/reopen', name: 'responsable_fault_reopen', methods: ['POST'])]
    public function reopenFault(Request $request, Fault $fault, ResponsableWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid('responsable_fault_reopen_'.$fault->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $workflow->reopenFault($fault, $this->responsable(), (string) $request->request->get('reason', ''));
            $this->addFlash('success', 'Panne réouverte.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('machine_fiche', ['id' => $fault->getMachine()->getId()]);
    }

    #[Route('/schedules/{id}/postpone', name: 'responsable_schedule_postpone', methods: ['POST'])]
    public function postpone(Request $request, MaintenanceSchedule $schedule, ResponsableWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid('responsable_schedule_postpone_'.$schedule->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $dateValue = (string) $request->request->get('new_date', '');
            $newDate = \DateTime::createFromFormat('!Y-m-d', $dateValue);
            $dateErrors = \DateTime::getLastErrors();
            if ($newDate === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                throw new \InvalidArgumentException('La nouvelle date est invalide.');
            }
            $workflow->postpone($schedule, $this->responsable(), $newDate, (string) $request->request->get('reason', ''));
            $this->addFlash('success', 'Tâche reportée.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('machine_fiche', ['id' => $schedule->getMachine()->getId()]);
    }

    private function responsable(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function entityOrNull(object $repository, mixed $id, string $class): ?object
    {
        if ($id === null || $id === '') {
            return null;
        }
        $entity = $repository->find((int) $id);

        return $entity instanceof $class ? $entity : null;
    }
}
