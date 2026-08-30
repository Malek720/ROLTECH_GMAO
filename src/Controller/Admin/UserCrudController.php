<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            EmailField::new('email', 'Email'),
            TextField::new('fullName', 'Nom complet'),

            // Read-only badge display on index/detail pages
            ArrayField::new('roles', 'Rôle')
                ->setTemplatePath('admin/field/role_badge.html.twig')
                ->onlyOnIndex(),

            // Editable multi-select on the form pages
            ChoiceField::new('roles', 'Rôle')
                ->setChoices([
                    'Administrateur' => 'ROLE_ADMIN',
                    'Responsable' => 'ROLE_RESPONSABLE',
                    'Technicien' => 'ROLE_TECHNICIEN',
                ])
                ->allowMultipleChoices()
                ->renderExpanded()
                ->onlyOnForms(),

            // Password is intentionally not editable here yet — changing it
            // correctly requires wiring Symfony's password hasher into the
            // form (e.g. via an event listener), which isn't set up yet.
            // Use `php bin/console security:hash-password` or a dedicated
            // "change password" action for now.
        ];
    }
}
