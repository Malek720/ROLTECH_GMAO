<?php

namespace App\Controller\Admin;

use App\Entity\Intervention;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class InterventionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Intervention::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            AssociationField::new('fault', 'Panne'),
            AssociationField::new('technician', 'Technicien'),
            DateTimeField::new('startDate', 'Début'),
            DateTimeField::new('endDate', 'Fin')->hideOnIndex(),
            TextareaField::new('comment', 'Commentaire')->hideOnIndex(),

            TextField::new('type', 'Type')
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'preventive' => ['icon' => 'fa-shield-halved', 'color' => '#5e81ac', 'label' => 'Préventive'],
                    'curative' => ['icon' => 'fa-wrench', 'color' => '#d08770', 'label' => 'Curative'],
                ])
                ->setFormType(TextType::class),

            TextField::new('status', 'Statut')
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'planifiee' => ['icon' => 'fa-calendar', 'color' => '#88c0d0', 'label' => 'Planifiée'],
                    'en cours' => ['icon' => 'fa-spinner', 'color' => '#d08770', 'label' => 'En cours'],
                    'terminee' => ['icon' => 'fa-circle-check', 'color' => '#a3be8c', 'label' => 'Terminée'],
                ])
                ->setFormType(TextType::class),
        ];
    }
}
