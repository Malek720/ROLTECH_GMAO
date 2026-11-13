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

    #[ORM\ManyToOne(inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MachineModel $machineModel = null;

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

    #[ORM\Column]
    private int $operatingHoursCorrection = 0;

    #[ORM\ManyToOne(inversedBy: 'machines')]
    private ?Site $site = null;

    /**
     * @var Collection<int, Fault>
     */
    #[ORM\OneToMany(targetEntity: Fault::class, mappedBy: 'machine', orphanRemoval: true)]
    private Collection $faults;

    /** @var Collection<int, MaintenanceSchedule> */
    #[ORM\OneToMany(targetEntity: MaintenanceSchedule::class, mappedBy: 'machine', orphanRemoval: true)]
    private Collection $maintenanceSchedules;

    public function __construct()
    {
        $this->faults = new ArrayCollection();
        $this->maintenanceSchedules = new ArrayCollection();
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

    public function getMachineModel(): ?MachineModel
    {
        return $this->machineModel;
    }

    public function setMachineModel(MachineModel $machineModel): static
    {
        $this->machineModel = $machineModel;

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

    public function getOperatingHoursCorrection(): int
    {
        return $this->operatingHoursCorrection;
    }

    public function setOperatingHoursCorrection(int $operatingHoursCorrection): static
    {
        $this->operatingHoursCorrection = $operatingHoursCorrection;

        return $this;
    }

    public function getOperatingHours(?\DateTime $asOf = null): int
    {
        if ($this->installationDate === null) {
            return $this->operatingHoursCorrection;
        }

        $asOf ??= new \DateTime();
        $seconds = $asOf->getTimestamp() - $this->installationDate->getTimestamp();

        return max(0, (int) floor($seconds / 3600) + $this->operatingHoursCorrection);
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

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

    /** @return Collection<int, MaintenanceSchedule> */
    public function getMaintenanceSchedules(): Collection
    {
        return $this->maintenanceSchedules;
    }

    public function __toString(): string
{
    return $this->name ?? 'Machine #' . $this->id;
}
}
