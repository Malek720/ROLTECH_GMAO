<?php

namespace App\Entity;

use App\Repository\MachineModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineModelRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MACHINE_MODEL_CODE', fields: ['code'])]
class MachineModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $family = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxLoadKg = null;

    #[ORM\Column(length: 20)]
    private string $dataStatus = 'demo';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, Machine> */
    #[ORM\OneToMany(targetEntity: Machine::class, mappedBy: 'machineModel')]
    private Collection $machines;

    /** @var Collection<int, MachineModule> */
    #[ORM\OneToMany(targetEntity: MachineModule::class, mappedBy: 'machineModel', orphanRemoval: true)]
    private Collection $modules;

    /** @var Collection<int, MaintenanceTask> */
    #[ORM\OneToMany(targetEntity: MaintenanceTask::class, mappedBy: 'machineModel', orphanRemoval: true)]
    private Collection $maintenanceTasks;

    public function __construct()
    {
        $this->machines = new ArrayCollection();
        $this->modules = new ArrayCollection();
        $this->maintenanceTasks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getFamily(): ?string { return $this->family; }
    public function setFamily(string $family): static { $this->family = $family; return $this; }
    public function getMaxLoadKg(): ?int { return $this->maxLoadKg; }
    public function setMaxLoadKg(?int $maxLoadKg): static { $this->maxLoadKg = $maxLoadKg; return $this; }
    public function getDataStatus(): string { return $this->dataStatus; }
    public function setDataStatus(string $dataStatus): static { $this->dataStatus = $dataStatus; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    /** @return Collection<int, Machine> */
    public function getMachines(): Collection { return $this->machines; }
    /** @return Collection<int, MachineModule> */
    public function getModules(): Collection { return $this->modules; }
    public function addModule(MachineModule $module): static { if (!$this->modules->contains($module)) $this->modules->add($module); return $this; }
    /** @return Collection<int, MaintenanceTask> */
    public function getMaintenanceTasks(): Collection { return $this->maintenanceTasks; }
    public function addMaintenanceTask(MaintenanceTask $task): static { if (!$this->maintenanceTasks->contains($task)) $this->maintenanceTasks->add($task); return $this; }
    public function getMachineCount(): int { return $this->machines->count(); }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->code ?? 'Modèle', $this->name ?? '');
    }
}
