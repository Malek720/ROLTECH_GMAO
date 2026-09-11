<?php

namespace App\Controller\Admin;

use App\Entity\Machine;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MachineCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Machine::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            TextField::new('category', 'Icône')
                ->setTemplatePath('admin/field/lead_icon.html.twig')
                ->setCustomOption('badgeMap', [
                    'Presse' => ['icon' => 'fa-gears', 'color' => '#5e81ac'],
                    'Usinage' => ['icon' => 'fa-industry', 'color' => '#88c0d0'],
                    'Manutention' => ['icon' => 'fa-truck-ramp-box', 'color' => '#b48ead'],
                    'Utilitaire' => ['icon' => 'fa-plug', 'color' => '#4c566a'],
                ])
                ->onlyOnIndex()
                ->setLabel(false),

            TextField::new('name', 'Nom'),
            TextField::new('model', 'Modèle'),
            TextField::new('serialNumber', 'Numéro de série'),
            DateField::new('installationDate', 'Date d\'installation'),
            TextField::new('location', 'Emplacement'),

            TextField::new('category', 'Catégorie')
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'Presse' => ['icon' => 'fa-gears', 'color' => '#5e81ac', 'label' => 'Presse'],
                    'Usinage' => ['icon' => 'fa-industry', 'color' => '#88c0d0', 'label' => 'Usinage'],
                    'Manutention' => ['icon' => 'fa-truck-ramp-box', 'color' => '#b48ead', 'label' => 'Manutention'],
                    'Utilitaire' => ['icon' => 'fa-plug', 'color' => '#4c566a', 'label' => 'Utilitaire'],
                ])
                ->setFormType(\Symfony\Component\Form\Extension\Core\Type\TextType::class),

            TextField::new('status', 'Statut')
                ->setTemplatePath('admin/field/badge.html.twig')
                ->setCustomOption('badgeMap', [
                    'en service' => ['icon' => 'fa-circle-check', 'color' => '#a3be8c', 'label' => 'En service'],
                    'en panne' => ['icon' => 'fa-triangle-exclamation', 'color' => '#bf616a', 'label' => 'En panne'],
                    'en maintenance' => ['icon' => 'fa-screwdriver-wrench', 'color' => '#d08770', 'label' => 'En maintenance'],
                ])
                ->setFormType(\Symfony\Component\Form\Extension\Core\Type\TextType::class),
        ];
    }
}
