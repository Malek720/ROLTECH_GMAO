<?php

namespace App\Controller\Admin;

use App\Entity\Fault;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FaultCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Fault::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            AssociationField::new('machine', 'Machine'),
            AssociationField::new('reportedBy', 'Signalée par'),
            DateTimeField::new('reportedAt', 'Signalée le'),
            TextareaField::new('description', 'Description'),

            TextField::new('severity', 'Gravité')
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'critique' => ['icon' => 'fa-fire', 'color' => '#bf616a', 'label' => 'Critique'],
                    'moyenne' => ['icon' => 'fa-triangle-exclamation', 'color' => '#d08770', 'label' => 'Moyenne'],
                    'faible' => ['icon' => 'fa-circle-info', 'color' => '#88c0d0', 'label' => 'Faible'],
                ])
                ->setFormType(TextType::class),

            TextField::new('status', 'Statut')
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'ouverte' => ['icon' => 'fa-circle-exclamation', 'color' => '#bf616a', 'label' => 'Ouverte'],
                    'en cours' => ['icon' => 'fa-spinner', 'color' => '#d08770', 'label' => 'En cours'],
                    'resolue' => ['icon' => 'fa-circle-check', 'color' => '#a3be8c', 'label' => 'Résolue'],
                ])
                ->setFormType(TextType::class),
        ];
    }
}
