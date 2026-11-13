<?php

namespace App\Entity;

use App\Repository\MachineModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineModuleRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MACHINE_MODULE_MODEL_CODE', fields: ['machineModel', 'code'])]
class MachineModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $planche = null;

    #[ORM\ManyToOne(inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MachineModel $machineModel = null;

    #[ORM\Column(length: 20)]
    private string $dataStatus = 'demo';

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    /** @var Collection<int, Part> */
    #[ORM\OneToMany(targetEntity: Part::class, mappedBy: 'module', orphanRemoval: true)]
    private Collection $parts;

    /** @var Collection<int, MaintenanceTask> */
    #[ORM\OneToMany(targetEntity: MaintenanceTask::class, mappedBy: 'module')]
    private Collection $maintenanceTasks;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->parts = new ArrayCollection();
        $this->maintenanceTasks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getPlanche(): ?string { return $this->planche; }
    public function setPlanche(string $planche): static { $this->planche = $planche; return $this; }
    public function getMachineModel(): ?MachineModel { return $this->machineModel; }
    public function setMachineModel(MachineModel $machineModel): static { $this->machineModel = $machineModel; return $this; }
    public function getDataStatus(): string { return $this->dataStatus; }
    public function setDataStatus(string $dataStatus): static { $this->dataStatus = $dataStatus; return $this; }
    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): static { $this->parent = $parent; return $this; }

    /** @return Collection<int, self> */
    public function getChildren(): Collection { return $this->children; }
    /** @return Collection<int, Part> */
    public function getParts(): Collection { return $this->parts; }
    public function addPart(Part $part): static { if (!$this->parts->contains($part)) $this->parts->add($part); return $this; }
    /** @return Collection<int, MaintenanceTask> */
    public function getMaintenanceTasks(): Collection { return $this->maintenanceTasks; }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->code ?? 'Module', $this->name ?? '');
    }
}
