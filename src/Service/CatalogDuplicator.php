<?php

namespace App\Service;

use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceTask;
use App\Entity\Part;
use Doctrine\ORM\EntityManagerInterface;

final class CatalogDuplicator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function duplicate(MachineModel $source, string $code, string $name): MachineModel
    {
        $code = trim($code);
        $name = trim($name);
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('Le code et le nom du modèle sont obligatoires.');
        }

        $repository = $this->entityManager->getRepository(MachineModel::class);
        $baseCode = $code;
        $suffix = 1;
        while ($repository->findOneBy(['code' => $code]) !== null) {
            $code = $baseCode.'-COPY'.($suffix > 1 ? '-'.$suffix : '');
            $suffix++;
        }

        $copy = (new MachineModel())
            ->setCode($code)
            ->setName($name)
            ->setFamily($source->getFamily() ?? '')
            ->setMaxLoadKg($source->getMaxLoadKg())
            ->setDataStatus('demo')
            ->setNotes($source->getNotes());
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        $this->entityManager->persist($copy);

        $modules = [];
        foreach ($source->getModules() as $module) {
            if ($module->getParent() !== null) {
                continue;
            }
            $this->copyModuleTree($module, $copy, null, $modules);
        }
        foreach ($source->getMaintenanceTasks() as $task) {
            $copyTask = (new MaintenanceTask())
                ->setMachineModel($copy)
                ->setAction($task->getAction() ?? '')
                ->setFrequencyDays($task->getFrequencyDays())
                ->setFrequencyHours($task->getFrequencyHours())
                ->setProcedureChecklist($task->getProcedureChecklist())
                ->setDataStatus('demo');
            $copy->addMaintenanceTask($copyTask);
            if ($task->getModule() !== null) {
                $copyTask->setModule($modules[$task->getModule()->getId()] ?? null);
            }
            if ($task->getPart() !== null && $task->getModule() !== null) {
                $newModule = $modules[$task->getModule()->getId()] ?? null;
                if ($newModule !== null) {
                    foreach ($newModule->getParts() as $part) {
                        if ($part->getDescription() === $task->getPart()->getDescription()) {
                            $copyTask->setPart($part);
                            break;
                        }
                    }
                }
            }
            $this->entityManager->persist($copyTask);
        }

        try {
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $copy;
    }

    /** @param array<int, MachineModule> $modules */
    private function copyModuleTree(MachineModule $source, MachineModel $model, ?MachineModule $parent, array &$modules): MachineModule
    {
        $copy = (new MachineModule())
            ->setMachineModel($model)
            ->setCode($source->getCode() ?? '')
            ->setName($source->getName() ?? '')
            ->setPlanche($source->getPlanche() ?? '')
            ->setDataStatus('demo')
            ->setParent($parent);
        $this->entityManager->persist($copy);
        $model->addModule($copy);
        $modules[$source->getId()] = $copy;

        foreach ($source->getParts() as $part) {
            $copyPart = (new Part())
                ->setModule($copy)
                ->setPartNumber(null)
                ->setDescription($part->getDescription() ?? '')
                ->setQuantity($part->getQuantity())
                ->setDataStatus('demo');
            $copy->addPart($copyPart);
            $this->entityManager->persist($copyPart);
        }
        foreach ($source->getChildren() as $child) {
            $this->copyModuleTree($child, $model, $copy, $modules);
        }

        return $copy;
    }
}
