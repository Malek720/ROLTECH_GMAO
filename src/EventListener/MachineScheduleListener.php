<?php

namespace App\EventListener;

use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Service\MachineScheduleGenerator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class MachineScheduleListener
{
    /** @var list<array{machine: Machine, previousModel: ?MachineModel}> */
    private array $machines = [];
    private bool $generating = false;

    public function __construct(private MachineScheduleGenerator $generator)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->generating) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Machine) {
                $this->queue($entity, null);
            }
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Machine) {
                continue;
            }
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if (array_key_exists('machineModel', $changeSet)) {
                $this->queue($entity, $changeSet['machineModel'][0] instanceof MachineModel ? $changeSet['machineModel'][0] : null);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->generating || $this->machines === []) {
            return;
        }

        $machines = $this->machines;
        $this->machines = [];
        $this->generating = true;
        try {
            foreach ($machines as $item) {
                $this->generator->synchronize($item['machine'], $item['previousModel']);
            }
            $args->getObjectManager()->flush();
        } finally {
            $this->generating = false;
        }
    }

    private function queue(Machine $machine, ?MachineModel $previousModel): void
    {
        foreach ($this->machines as $item) {
            if ($item['machine'] === $machine) {
                return;
            }
        }
        $this->machines[] = ['machine' => $machine, 'previousModel' => $previousModel];
    }
}
