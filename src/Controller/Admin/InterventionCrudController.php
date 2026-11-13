<?php

namespace App\Controller\Admin;

use App\Entity\Intervention;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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

            AssociationField::new('fault', 'Panne')
                ->setRequired(false)
                ->setHelp('Intervention curative : sélectionner une panne uniquement.'),
            AssociationField::new('schedule', 'Planning')
                ->setRequired(false)
                ->setHelp('Intervention préventive : sélectionner un planning uniquement.'),
            AssociationField::new('technician', 'Technicien'),
            AssociationField::new('assignedBy', 'Affectée par')->setRequired(false),
            ChoiceField::new('assignmentSource', 'Origine')
                ->setChoices(['Système' => 'systeme', 'Responsable' => 'responsable']),
            DateTimeField::new('startDate', 'Début'),
            DateTimeField::new('endDate', 'Fin')->hideOnIndex(),
            ChoiceField::new('type', 'Type')
                ->setChoices(['Préventive' => 'preventive', 'Curative' => 'curative'])
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'preventive' => ['icon' => 'fa-shield-halved', 'color' => '#5e81ac', 'label' => 'Préventive'],
                    'curative' => ['icon' => 'fa-wrench', 'color' => '#d08770', 'label' => 'Curative'],
                ])
                ,

            ChoiceField::new('status', 'Statut')
                ->setChoices(['Planifiée' => 'planifiee', 'En cours' => 'en cours', 'Terminée' => 'terminee'])
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'planifiee' => ['icon' => 'fa-calendar', 'color' => '#88c0d0', 'label' => 'Planifiée'],
                    'en cours' => ['icon' => 'fa-spinner', 'color' => '#d08770', 'label' => 'En cours'],
                    'terminee' => ['icon' => 'fa-circle-check', 'color' => '#a3be8c', 'label' => 'Terminée'],
                ])
                ,
            TextareaField::new('comment', 'Commentaire')
                ->setHelp('Sécurité : couper et verrouiller les alimentations pneumatique et électrique. Personnel qualifié uniquement. Pièces originales ou validées par ROLTECH.')
                ->hideOnIndex(),
        ];
    }
}
