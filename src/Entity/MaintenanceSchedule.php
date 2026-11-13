<?php

namespace App\Entity;

use App\Repository\MaintenanceScheduleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenanceScheduleRepository::class)]
class MaintenanceSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $lastDoneDate = null;

    #[ORM\Column]
    private ?int $lastDoneHours = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $nextDueDate = null;

    #[ORM\Column]
    private ?int $nextDueHours = null;

    #[ORM\Column(length: 50)]
    private ?string $state = 'ok';

    #[ORM\Column(nullable: true)]
    private ?\DateTime $archivedAt = null;

    #[ORM\ManyToOne(inversedBy: 'maintenanceSchedules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Machine $machine = null;

    #[ORM\ManyToOne(inversedBy: 'schedules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MaintenanceTask $task = null;

    /** @var Collection<int, Intervention> */
    #[ORM\OneToMany(targetEntity: Intervention::class, mappedBy: 'schedule')]
    private Collection $interventions;

    public function __construct()
    {
        $this->interventions = new ArrayCollection();
    }

    public function initializeFromMachine(Machine $machine, MaintenanceTask $task, ?\DateTime $createdAt = null): static
    {
        $date = $createdAt ?? new \DateTime();
        $this->machine = $machine;
        $this->task = $task;
        $this->lastDoneDate = clone $date;
        $this->lastDoneHours = $machine->getOperatingHours($date);
        $this->nextDueDate = $task->getFrequencyDays() === null
            ? clone $date
            : (clone $date)->modify(sprintf('+%d days', $task->getFrequencyDays()));
        $this->nextDueHours = $task->getFrequencyHours() === null
            ? $this->lastDoneHours
            : $this->lastDoneHours + $task->getFrequencyHours();
        $this->state = 'ok';

        return $this;
    }

    public function getId(): ?int { return $this->id; }
    public function getLastDoneDate(): ?\DateTime { return $this->lastDoneDate; }
    public function setLastDoneDate(\DateTime $value): static { $this->lastDoneDate = $value; return $this; }
    public function getLastDoneHours(): ?int { return $this->lastDoneHours; }
    public function setLastDoneHours(int $value): static { $this->lastDoneHours = $value; return $this; }
    public function getNextDueDate(): ?\DateTime { return $this->nextDueDate; }
    public function setNextDueDate(\DateTime $value): static { $this->nextDueDate = $value; return $this; }
    public function getNextDueHours(): ?int { return $this->nextDueHours; }
    public function setNextDueHours(int $value): static { $this->nextDueHours = $value; return $this; }
    public function getState(): ?string { return $this->state; }
    public function setState(string $state): static { $this->state = $state; return $this; }
    public function getArchivedAt(): ?\DateTime { return $this->archivedAt; }
    public function setArchivedAt(?\DateTime $archivedAt): static { $this->archivedAt = $archivedAt; return $this; }
    public function getMachine(): ?Machine { return $this->machine; }
    public function setMachine(?Machine $machine): static { $this->machine = $machine; return $this; }
    public function getTask(): ?MaintenanceTask { return $this->task; }
    public function setTask(?MaintenanceTask $task): static { $this->task = $task; return $this; }

    /** @return Collection<int, Intervention> */
    public function getInterventions(): Collection { return $this->interventions; }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->machine ?? 'Machine', $this->task ?? 'Tâche');
    }
}
