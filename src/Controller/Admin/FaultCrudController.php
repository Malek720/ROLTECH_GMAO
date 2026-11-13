<?php

namespace App\Controller\Admin;

use App\Entity\Fault;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

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
            AssociationField::new('module', 'Module')->setRequired(false),
            AssociationField::new('part', 'Pièce')->setRequired(false),
            ChoiceField::new('symptom', 'Symptôme')
                ->setChoices([
                    'Démarrage / arrêt fréquent du moteur' => 'Conveyor does not start or motor stops frequently',
                    'Craquement aux disques de renvoi' => 'Cracking noise at the return discs (disques de renvoi)',
                    'Surchauffe moteur ou réducteur' => 'Motor or reducer overheating',
                    'Corde arrêtée, moteur en marche' => 'Rope stops but motor runs',
                    'Autre' => 'autre',
                ])->setRequired(false),
            DateTimeField::new('reportedAt', 'Signalée le'),
            TextareaField::new('description', 'Description'),

            ChoiceField::new('severity', 'Gravité')
                ->setChoices(['Critique' => 'critique', 'Moyenne' => 'moyenne', 'Faible' => 'faible'])
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'critique' => ['icon' => 'fa-fire', 'color' => '#bf616a', 'label' => 'Critique'],
                    'moyenne' => ['icon' => 'fa-triangle-exclamation', 'color' => '#d08770', 'label' => 'Moyenne'],
                    'faible' => ['icon' => 'fa-circle-info', 'color' => '#88c0d0', 'label' => 'Faible'],
                ])
                ,

            ChoiceField::new('status', 'Statut')
                ->setChoices(['Ouverte' => 'ouverte', 'En cours' => 'en cours', 'Résolue' => 'resolue'])
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'ouverte' => ['icon' => 'fa-circle-exclamation', 'color' => '#bf616a', 'label' => 'Ouverte'],
                    'en cours' => ['icon' => 'fa-spinner', 'color' => '#d08770', 'label' => 'En cours'],
                    'resolue' => ['icon' => 'fa-circle-check', 'color' => '#a3be8c', 'label' => 'Résolue'],
                ])
                ,
        ];
    }
}
