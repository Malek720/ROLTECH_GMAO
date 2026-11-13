<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return Notification::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('recipient', 'Destinataire'),
            TextareaField::new('message', 'Message'),
            ChoiceField::new('type', 'Type')
                ->setChoices([
                    'Panne' => 'panne',
                    'Intervention' => 'intervention',
                    'Maintenance' => 'maintenance',
                ]),
            DateTimeField::new('createdAt', 'Créée le'),
            DateTimeField::new('readAt', 'Lue le')->setRequired(false),
            AssociationField::new('fault', 'Panne')->setRequired(false),
            AssociationField::new('intervention', 'Intervention')->setRequired(false),
        ];
    }
}
