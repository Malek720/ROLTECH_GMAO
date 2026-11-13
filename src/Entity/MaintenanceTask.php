<?php

namespace App\Entity;

use App\Repository\MaintenanceTaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenanceTaskRepository::class)]
class MaintenanceTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $action = null;

    #[ORM\Column(nullable: true)]
    private ?int $frequencyDays = null;

    #[ORM\Column(nullable: true)]
    private ?int $frequencyHours = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $procedureChecklist = null;

    #[ORM\ManyToOne(inversedBy: 'maintenanceTasks')]
    private ?MachineModule $module = null;

    #[ORM\ManyToOne(inversedBy: 'maintenanceTasks')]
    private ?Part $part = null;

    #[ORM\ManyToOne(inversedBy: 'maintenanceTasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MachineModel $machineModel = null;

    #[ORM\Column(length: 20)]
    private string $dataStatus = 'demo';

    /** @var Collection<int, MaintenanceSchedule> */
    #[ORM\OneToMany(targetEntity: MaintenanceSchedule::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $schedules;

    public function __construct()
    {
        $this->schedules = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getFrequencyDays(): ?int { return $this->frequencyDays; }
    public function setFrequencyDays(?int $frequencyDays): static { $this->frequencyDays = $frequencyDays; return $this; }
    public function getFrequencyHours(): ?int { return $this->frequencyHours; }
    public function setFrequencyHours(?int $frequencyHours): static { $this->frequencyHours = $frequencyHours; return $this; }
    public function getProcedureChecklist(): ?string { return $this->procedureChecklist; }
    public function setProcedureChecklist(?string $procedureChecklist): static { $this->procedureChecklist = $procedureChecklist; return $this; }
    public function getModule(): ?MachineModule { return $this->module; }
    public function setModule(?MachineModule $module): static { $this->module = $module; return $this; }
    public function getPart(): ?Part { return $this->part; }
    public function setPart(?Part $part): static { $this->part = $part; return $this; }
    public function getMachineModel(): ?MachineModel { return $this->machineModel; }
    public function setMachineModel(MachineModel $machineModel): static { $this->machineModel = $machineModel; return $this; }
    public function getDataStatus(): string { return $this->dataStatus; }
    public function setDataStatus(string $dataStatus): static { $this->dataStatus = $dataStatus; return $this; }

    /** @return Collection<int, MaintenanceSchedule> */
    public function getSchedules(): Collection { return $this->schedules; }

    public function __toString(): string
    {
        return $this->action ?? 'Tâche de maintenance #'.$this->id;
    }
}
