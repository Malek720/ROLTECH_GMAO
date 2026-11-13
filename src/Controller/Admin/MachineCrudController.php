<?php

namespace App\Controller\Admin;

use App\Entity\Machine;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use App\Admin\Filter\RelatedTextFilter;

class MachineCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Machine::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::new('fiche', 'Fiche')
            ->linkToRoute('machine_fiche', static fn (Machine $machine): array => ['id' => $machine->getId()]));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('machineModel', 'Modèle'))
            ->add(RelatedTextFilter::new('machineModel', 'family', 'Famille'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            TextField::new('category', 'Icône')
                ->setTemplatePath('admin/field/lead_icon.html.twig')
                ->setCustomOption('badgeMap', [
                    'Convoyeur aérien' => ['icon' => 'fa-arrows-left-right', 'color' => '#5e81ac', 'label' => 'Convoyeur aérien'],
                    'Presse' => ['icon' => 'fa-gears', 'color' => '#5e81ac'],
                    'Usinage' => ['icon' => 'fa-industry', 'color' => '#88c0d0'],
                    'Manutention' => ['icon' => 'fa-truck-ramp-box', 'color' => '#b48ead'],
                    'Utilitaire' => ['icon' => 'fa-plug', 'color' => '#4c566a'],
                ])
                ->onlyOnIndex()
                ->setLabel(false),

            TextField::new('name', 'Nom'),
            AssociationField::new('machineModel', 'Modèle')->setFormTypeOption('choice_label', static fn (\App\Entity\MachineModel $model): string => sprintf('%s - %s (%s)%s', $model->getCode(), $model->getName(), $model->getFamily(), $model->getDataStatus() === 'demo' ? ' — Données de démonstration' : '')),
            TextField::new('serialNumber', 'Numéro de série'),
            DateField::new('installationDate', 'Date d\'installation'),
            TextField::new('location', 'Emplacement'),
            AssociationField::new('site', 'Site'),
            IntegerField::new('operatingHoursCorrection', 'Correction heures')->hideOnIndex(),
            IntegerField::new('operatingHours', 'Heures de fonctionnement')->onlyOnDetail()->setFormTypeOption('mapped', false),

            ChoiceField::new('category', 'Catégorie')
                ->setChoices([
                    'Convoyeur aérien' => 'Convoyeur aérien',
                    'Presse' => 'Presse',
                    'Usinage' => 'Usinage',
                    'Manutention' => 'Manutention',
                    'Utilitaire' => 'Utilitaire',
                ])
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'Presse' => ['icon' => 'fa-gears', 'color' => '#5e81ac', 'label' => 'Presse'],
                    'Usinage' => ['icon' => 'fa-industry', 'color' => '#88c0d0', 'label' => 'Usinage'],
                    'Manutention' => ['icon' => 'fa-truck-ramp-box', 'color' => '#b48ead', 'label' => 'Manutention'],
                    'Utilitaire' => ['icon' => 'fa-plug', 'color' => '#4c566a', 'label' => 'Utilitaire'],
                ])
                ,

            ChoiceField::new('status', 'Statut')
                ->setChoices(['En service' => 'en service', 'En panne' => 'en panne', 'En maintenance' => 'en maintenance'])
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'en service' => ['icon' => 'fa-circle-check', 'color' => '#a3be8c', 'label' => 'En service'],
                    'en panne' => ['icon' => 'fa-triangle-exclamation', 'color' => '#bf616a', 'label' => 'En panne'],
                    'en maintenance' => ['icon' => 'fa-screwdriver-wrench', 'color' => '#d08770', 'label' => 'En maintenance'],
                ])
                ,
        ];
    }
}
