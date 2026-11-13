<?php

namespace App\Entity;

use App\Repository\InterventionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Intervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column]
    private ?\DateTime $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 30)]
    private string $assignmentSource = 'systeme';

    #[ORM\ManyToOne]
    private ?User $assignedBy = null;

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Fault $fault = null;

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $technician = null;

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    private ?MaintenanceSchedule $schedule = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getAssignmentSource(): string
    {
        return $this->assignmentSource;
    }

    public function setAssignmentSource(string $assignmentSource): static
    {
        $this->assignmentSource = $assignmentSource;

        return $this;
    }

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getFault(): ?Fault
    {
        return $this->fault;
    }

    public function setFault(?Fault $fault): static
    {
        $this->fault = $fault;

        return $this;
    }

    public function getTechnician(): ?User
    {
        return $this->technician;
    }

    public function setTechnician(?User $technician): static
    {
        $this->technician = $technician;

        return $this;
    }

    public function getSchedule(): ?MaintenanceSchedule
    {
        return $this->schedule;
    }

    public function setSchedule(?MaintenanceSchedule $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function validateSourceLifecycle(PrePersistEventArgs|PreUpdateEventArgs $event): void
    {
        $this->assertValidSource();
    }

    #[Assert\Callback]
    public function validateSource(?ExecutionContextInterface $context = null): void
    {
        if (!$this->hasInvalidSource()) {
            return;
        }

        $message = 'Sélectionnez soit une panne, soit un planning, mais pas les deux.';
        if ($context !== null) {
            $context->buildViolation($message)
                ->atPath('fault')
                ->addViolation();

            return;
        }

        $this->assertValidSource();
    }

    private function hasInvalidSource(): bool
    {
        return ($this->fault === null) === ($this->schedule === null);
    }

    private function assertValidSource(): void
    {
        if ($this->hasInvalidSource()) {
            throw new \InvalidArgumentException('Une intervention doit être liée à une panne ou à un planning, mais pas aux deux.');
        }
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->type ?? 'Intervention', $this->startDate?->format('d/m/Y') ?? '');
    }
}
