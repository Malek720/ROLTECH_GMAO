<?php

namespace App\Entity;

use App\Repository\PartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartRepository::class)]
class Part
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $partNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(length: 20)]
    private string $dataStatus = 'demo';

    #[ORM\ManyToOne(inversedBy: 'parts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MachineModule $module = null;

    /** @var Collection<int, MaintenanceTask> */
    #[ORM\OneToMany(targetEntity: MaintenanceTask::class, mappedBy: 'part')]
    private Collection $maintenanceTasks;

    public function __construct()
    {
        $this->maintenanceTasks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPartNumber(): ?string { return $this->partNumber; }
    public function setPartNumber(?string $partNumber): static { $this->partNumber = $partNumber; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(?int $quantity): static { $this->quantity = $quantity; return $this; }
    public function getDataStatus(): string { return $this->dataStatus; }
    public function setDataStatus(string $dataStatus): static { $this->dataStatus = $dataStatus; return $this; }
    public function getModule(): ?MachineModule { return $this->module; }
    public function setModule(?MachineModule $module): static { $this->module = $module; return $this; }

    /** @return Collection<int, MaintenanceTask> */
    public function getMaintenanceTasks(): Collection { return $this->maintenanceTasks; }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->partNumber ?? 'Part', $this->description ?? '');
    }
}
