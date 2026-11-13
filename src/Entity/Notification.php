<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $readAt = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[ORM\ManyToOne]
    private ?Fault $fault = null;

    #[ORM\ManyToOne]
    private ?Intervention $intervention = null;

    public function getId(): ?int { return $this->id; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getReadAt(): ?\DateTime { return $this->readAt; }
    public function setReadAt(?\DateTime $readAt): static { $this->readAt = $readAt; return $this; }
    public function getRecipient(): ?User { return $this->recipient; }
    public function setRecipient(?User $recipient): static { $this->recipient = $recipient; return $this; }
    public function getFault(): ?Fault { return $this->fault; }
    public function setFault(?Fault $fault): static { $this->fault = $fault; return $this; }
    public function getIntervention(): ?Intervention { return $this->intervention; }
    public function setIntervention(?Intervention $intervention): static { $this->intervention = $intervention; return $this; }

    public function __toString(): string
    {
        return $this->message ?? 'Notification #'.$this->id;
    }
}
