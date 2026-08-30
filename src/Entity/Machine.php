<?php

namespace App\Entity;

use App\Repository\MachineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
class Machine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $model = null;

    #[ORM\Column(length: 255)]
    private ?string $serialNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $installationDate = null;

    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    /**
     * @var Collection<int, Fault>
     */
    #[ORM\OneToMany(targetEntity: Fault::class, mappedBy: 'machine', orphanRemoval: true)]
    private Collection $faults;

    public function __construct()
    {
        $this->faults = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;

        return $this;
    }

    public function getInstallationDate(): ?\DateTime
    {
        return $this->installationDate;
    }

    public function setInstallationDate(\DateTime $installationDate): static
    {
        $this->installationDate = $installationDate;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

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

    /**
     * @return Collection<int, Fault>
     */
    public function getFaults(): Collection
    {
        return $this->faults;
    }

    public function addFault(Fault $fault): static
    {
        if (!$this->faults->contains($fault)) {
            $this->faults->add($fault);
            $fault->setMachine($this);
        }

        return $this;
    }

    public function removeFault(Fault $fault): static
    {
        if ($this->faults->removeElement($fault)) {
            // set the owning side to null (unless already changed)
            if ($fault->getMachine() === $this) {
                $fault->setMachine(null);
            }
        }

        return $this;
    }
    public function __toString(): string
{
    return $this->name ?? 'Machine #' . $this->id;
}
}
