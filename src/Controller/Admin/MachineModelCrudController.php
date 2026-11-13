<?php

namespace App\Controller\Admin;

use App\Entity\MachineModel;
use App\Service\CatalogCsvService;
use App\Service\CatalogDuplicator;
use App\Service\MachineScheduleGenerator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[IsGranted('ROLE_ADMIN')]
class MachineModelCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CatalogDuplicator $duplicator,
        private readonly CatalogCsvService $csv,
        private readonly MachineScheduleGenerator $scheduleGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string { return MachineModel::class; }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::new('catalogue', 'Catalogue')->linkToRoute('admin_model_detail', static fn (MachineModel $model): array => ['id' => $model->getId()]))
            ->add(Crud::PAGE_INDEX, Action::new('duplicate', 'Préparer la duplication')->linkToRoute('admin_model_duplicate', static fn (MachineModel $model): array => ['id' => $model->getId()]))
            ->add(Crud::PAGE_INDEX, Action::new('export', 'Exporter CSV')->linkToRoute('admin_model_export', static fn (MachineModel $model): array => ['id' => $model->getId()]))
            ->add(Crud::PAGE_INDEX, Action::new('import', 'Préparer l’import')->linkToRoute('admin_model_import', static fn (MachineModel $model): array => ['id' => $model->getId()]));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('code', 'Code'),
            TextField::new('name', 'Nom'),
            TextField::new('family', 'Famille'),
            IntegerField::new('machineCount', 'Machines')->onlyOnIndex(),
            IntegerField::new('maxLoadKg', 'Charge max. (kg)')->setRequired(false)->formatValue(static fn ($value) => $value ?? 'charge max. à confirmer'),
            ChoiceField::new('dataStatus', 'Données')->setChoices(['Données confirmées' => 'confirmed', 'Données de démonstration' => 'demo'])->setTemplatePath('admin/field/badge.html.twig'),
            TextareaField::new('notes', 'Notes')->setRequired(false),
            AssociationField::new('machines', 'Machines')->hideOnForm(),
            AssociationField::new('modules', 'Modules')->hideOnForm(),
            AssociationField::new('maintenanceTasks', 'Tâches')->hideOnForm(),
        ];
    }

    public function configureFilters(\EasyCorp\Bundle\EasyAdminBundle\Config\Filters $filters): \EasyCorp\Bundle\EasyAdminBundle\Config\Filters
    {
        return $filters->add(TextFilter::new('family', 'Famille'))->add(ChoiceFilter::new('dataStatus', 'Données')->setChoices(['Confirmées' => 'confirmed', 'Démonstration' => 'demo']));
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MachineModel) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }
        $machines = $entityInstance->getMachines()->count();
        $schedules = $this->entityManager->getRepository(MachineModel::class)->countBlockingSchedules($entityInstance);
        if ($machines > 0 || $schedules > 0) {
            $reasons = [];
            if ($machines > 0) $reasons[] = $machines.' machine(s)';
            if ($schedules > 0) $reasons[] = $schedules.' planning(s), y compris archivé(s)';
            $this->addFlash('error', 'Suppression refusée : le modèle est utilisé par '.implode(' et ', $reasons).'.');
            return;
        }
        $entityManager->wrapInTransaction(function () use ($entityManager, $entityInstance): void {
            $entityManager->remove($entityInstance);
            $entityManager->flush();
        });
        $this->addFlash('success', 'Modèle supprimé.');
    }

    #[Route('/admin/model/{id}/detail', name: 'admin_model_detail', methods: ['GET'])]
    public function catalogDetail(MachineModel $model): Response
    {
        return $this->render('admin/model/detail.html.twig', ['model' => $model, 'regenerateCsrfToken' => $this->csrfTokenManager->getToken('regenerate-schedules')->getValue()]);
    }

    #[Route('/admin/model/{id}/duplicate', name: 'admin_model_duplicate', methods: ['GET', 'POST'])]
    public function duplicate(Request $request, MachineModel $model): Response
    {
        if (!$request->isMethod('POST')) return $this->render('admin/model/duplicate.html.twig', ['model' => $model, 'csrfToken' => $this->csrfTokenManager->getToken('duplicate-model')->getValue()]);
        $this->requireCsrf($request, 'duplicate-model');
        $copy = $this->duplicator->duplicate($model, (string) $request->request->get('code'), (string) $request->request->get('name'));
        $this->addFlash('success', sprintf('Le modèle %s a été dupliqué sous le code %s.', $model->getCode(), $copy->getCode()));
        return $this->redirectToRoute('admin_model_detail', ['id' => $copy->getId()]);
    }

    #[Route('/admin/model/{id}/export', name: 'admin_model_export', methods: ['GET'])]
    public function export(MachineModel $model): Response
    {
        $response = new Response($this->csv->export($model));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', ResponseHeaderBag::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $model->getCode().'-catalogue.csv'));
        return $response;
    }

    #[Route('/admin/model/{id}/import', name: 'admin_model_import', methods: ['GET', 'POST'])]
    public function import(Request $request, MachineModel $model): Response
    {
        if (!$request->isMethod('POST')) return $this->render('admin/model/import.html.twig', ['model' => $model, 'csrfToken' => $this->csrfTokenManager->getToken('catalog-upload')->getValue()]);
        $this->requireCsrf($request, 'catalog-upload');
        $file = $request->files->get('catalogue');
        if (!$file || $file->getSize() === null || $file->getSize() > 1024 * 1024 || strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)) !== 'csv') {
            return $this->safeImportError($request, $model, 'Le fichier doit être un CSV de 1 Mo maximum.');
        }
        $contents = file_get_contents($file->getPathname());
        if (!is_string($contents) || str_contains($contents, "\0") || !mb_check_encoding($contents, 'UTF-8')) {
            return $this->safeImportError($request, $model, 'Le fichier doit être un CSV texte encodé en UTF-8.');
        }
        $nonce = bin2hex(random_bytes(16));
        $preview = $this->csv->preview($contents, $model);
        $request->getSession()->set('catalog_import_'.$nonce, ['modelId' => $model->getId(), 'contents' => $contents, 'hash' => hash('sha256', $contents), 'createdAt' => time()]);
        return $this->render('admin/model/import_preview.html.twig', ['model' => $model, 'preview' => $preview, 'nonce' => $nonce, 'csrfToken' => $this->csrfTokenManager->getToken('catalog-import-confirm')->getValue()]);
    }

    #[Route('/admin/model/{id}/import/confirm', name: 'admin_model_import_confirm', methods: ['POST'])]
    public function confirmImport(Request $request, MachineModel $model): Response
    {
        $this->requireCsrf($request, 'catalog-import-confirm');
        $nonce = (string) $request->request->get('nonce');
        $entry = $request->getSession()->get('catalog_import_'.$nonce);
        if (!is_array($entry) || $entry['modelId'] !== $model->getId() || time() - (int) $entry['createdAt'] > 900 || !hash_equals((string) $entry['hash'], hash('sha256', (string) $entry['contents']))) {
            $request->getSession()->remove('catalog_import_'.$nonce);
            $this->addFlash('error', 'L’aperçu d’importation est expiré ou invalide.');
            return $this->redirectToRoute('admin_model_import', ['id' => $model->getId()]);
        }
        $preview = $this->csv->preview((string) $entry['contents'], $model);
        if ($preview['errors'] !== []) {
            $this->addFlash('error', 'De nouvelles erreurs bloquantes empêchent l’importation.');
            return $this->render('admin/model/import_preview.html.twig', ['model' => $model, 'preview' => $preview, 'nonce' => $nonce, 'csrfToken' => $this->csrfTokenManager->getToken('catalog-import-confirm')->getValue()]);
        }
        $result = $this->csv->import((string) $entry['contents'], $model);
        $request->getSession()->remove('catalog_import_'.$nonce);
        $this->addFlash('success', sprintf('Import terminé : %d création(s), %d mise(s) à jour.', $result['created'], $result['updated']));
        return $this->redirectToRoute('admin_model_detail', ['id' => $model->getId()]);
    }

    #[Route('/admin/model/{id}/regenerate', name: 'admin_model_regenerate', methods: ['POST'])]
    public function regenerate(Request $request, MachineModel $model): Response
    {
        $this->requireCsrf($request, 'regenerate-schedules');
        $created = 0;
        foreach ($model->getMachines() as $machine) $created += $this->scheduleGenerator->synchronize($machine)['created'];
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%d planning(s) créé(s). Aucun planning existant n’a été supprimé ou archivé.', $created));
        return $this->redirectToRoute('admin_model_detail', ['id' => $model->getId()]);
    }

    private function requireCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton de sécurité est invalide.');
            throw new AccessDeniedHttpException('Jeton CSRF invalide.');
        }
    }

    private function safeImportError(Request $request, MachineModel $model, string $message): Response
    {
        $this->addFlash('error', $message);
        return $this->redirectToRoute('admin_model_import', ['id' => $model->getId()]);
    }
}
