<?php

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    /** @var Collection<int, Machine> */
    #[ORM\OneToMany(targetEntity: Machine::class, mappedBy: 'site')]
    private Collection $machines;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'site')]
    private Collection $users;

    public function __construct()
    {
        $this->machines = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): static { $this->city = $city; return $this; }

    /** @return Collection<int, Machine> */
    public function getMachines(): Collection { return $this->machines; }

    /** @return Collection<int, User> */
    public function getUsers(): Collection { return $this->users; }

    public function __toString(): string
    {
        return $this->name ?? 'Site #'.$this->id;
    }
}
