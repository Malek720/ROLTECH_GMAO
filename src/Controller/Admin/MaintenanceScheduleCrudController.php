<?php

namespace App\Controller\Admin;

use App\Entity\MaintenanceSchedule;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class MaintenanceScheduleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return MaintenanceSchedule::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('machine', 'Machine'),
            AssociationField::new('task', 'Tâche'),
            DateField::new('lastDoneDate', 'Dernière réalisation'),
            IntegerField::new('lastDoneHours', 'Heures dernière réalisation'),
            DateField::new('nextDueDate', 'Prochaine échéance'),
            IntegerField::new('nextDueHours', 'Heures prochaine échéance'),
            ChoiceField::new('state', 'État')
                ->setChoices(['OK' => 'ok', 'Proche' => 'proche', 'Échue' => 'echue'])
                ->setTemplatePath('admin/field/badge.html.twig'),
            DateField::new('archivedAt', 'Archivée le')->hideOnForm(),
        ];
    }
}
