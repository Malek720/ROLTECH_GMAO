<?php

namespace App\Entity;

use App\Repository\InterventionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
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

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fault $fault = null;

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $technician = null;

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
}
