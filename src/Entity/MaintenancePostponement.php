<?php

namespace App\Entity;

use App\Repository\MaintenancePostponementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenancePostponementRepository::class)]
class MaintenancePostponement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'postponements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MaintenanceSchedule $schedule = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $postponedBy = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $previousDueDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $newDueDate = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    public function getId(): ?int { return $this->id; }
    public function getSchedule(): ?MaintenanceSchedule { return $this->schedule; }
    public function setSchedule(?MaintenanceSchedule $schedule): static { $this->schedule = $schedule; return $this; }
    public function getPostponedBy(): ?User { return $this->postponedBy; }
    public function setPostponedBy(?User $postponedBy): static { $this->postponedBy = $postponedBy; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = $reason; return $this; }
    public function getPreviousDueDate(): ?\DateTime { return $this->previousDueDate; }
    public function setPreviousDueDate(\DateTime $previousDueDate): static { $this->previousDueDate = $previousDueDate; return $this; }
    public function getNewDueDate(): ?\DateTime { return $this->newDueDate; }
    public function setNewDueDate(\DateTime $newDueDate): static { $this->newDueDate = $newDueDate; return $this; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function __toString(): string
    {
        return sprintf('Report du %s au %s', $this->previousDueDate?->format('d/m/Y'), $this->newDueDate?->format('d/m/Y'));
    }
}
