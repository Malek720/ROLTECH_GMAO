<?php

namespace App\Controller\Admin;

use App\Entity\MaintenanceTask;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class MaintenanceTaskCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return MaintenanceTask::class; }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('machineModel', 'Modèle'))
            ->add(ChoiceFilter::new('dataStatus', 'Données')->setChoices(['Confirmées' => 'confirmed', 'Démonstration' => 'demo']));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextareaField::new('action', 'Action'),
            IntegerField::new('frequencyDays', 'Fréquence (jours)')->setRequired(false),
            IntegerField::new('frequencyHours', 'Fréquence (heures)')->setRequired(false),
            AssociationField::new('module', 'Module')->setRequired(false),
            AssociationField::new('part', 'Pièce')->setRequired(false),
            AssociationField::new('machineModel', 'Modèle'),
            ChoiceField::new('dataStatus', 'Statut des données')->setChoices(['Confirmées' => 'confirmed', 'Démonstration' => 'demo'])->setTemplatePath('admin/field/badge.html.twig'),
            TextareaField::new('procedureChecklist', 'Procédure / checklist')->setRequired(false),
        ];
    }
}
