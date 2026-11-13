<?php

namespace App\Controller\Admin;

use App\Entity\Part;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use App\Admin\Filter\RelatedTextFilter;

class PartCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return Part::class; }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(RelatedTextFilter::new('module.machineModel', 'family', 'Famille'))
            ->add(RelatedTextFilter::new('module.machineModel', 'code', 'Modèle'))
            ->add(ChoiceFilter::new('dataStatus', 'Données')->setChoices(['Confirmées' => 'confirmed', 'Démonstration' => 'demo']));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('partNumber', 'Référence')->formatValue(static fn ($value) => $value ?? 'référence à confirmer'),
            ChoiceField::new('dataStatus', 'Statut des données')->setChoices(['Confirmées' => 'confirmed', 'Démonstration' => 'demo'])->setTemplatePath('admin/field/badge.html.twig'),
            TextField::new('description', 'Description / type'),
            IntegerField::new('quantity', 'Quantité')->setRequired(false),
            AssociationField::new('module', 'Module'),
        ];
    }
}
