<?php

namespace App\Controller\Admin;

use App\Entity\MachineModule;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class MachineModuleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return MachineModule::class; }

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
            TextField::new('code', 'Module'),
            TextField::new('name', 'Désignation'),
            TextField::new('planche', 'Planche'),
            AssociationField::new('machineModel', 'Modèle'),
            \EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField::new('dataStatus', 'Statut des données')->setChoices(['Confirmées' => 'confirmed', 'Démonstration' => 'demo'])->setTemplatePath('admin/field/badge.html.twig'),
            AssociationField::new('parent', 'Module parent')->setRequired(false),
            CollectionField::new('children', 'Sous-modules')->hideOnForm(),
            CollectionField::new('parts', 'Pièces')->hideOnForm(),
        ];
    }
}
