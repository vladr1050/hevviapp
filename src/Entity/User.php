<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Index(name: 'idx_user_state', columns: ['state'])]
#[ORM\Index(name: 'idx_user_locale', columns: ['locale'])]
#[ORM\Index(name: 'idx_user_phone', columns: ['phone'])]
#[ORM\Index(name: 'idx_user_name', columns: ['first_name', 'last_name'])]
#[ORM\Index(name: 'idx_user_first_name', columns: ['first_name'])]
#[ORM\Index(name: 'idx_user_last_name', columns: ['last_name'])]
#[ORM\Table(name: '`user`')]
class User extends BaseSecurityDBO
{
    #[ORM\Column(length: 255)]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $locale = self::BASE_LOCALE['English'];

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $state = parent::BASE_STATE['ENABLED'];

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'sender')]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    public function setState(int $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setSender($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getSender() === $this) {
                $order->setSender(null);
            }
        }

        return $this;
    }

    public function getReadableStates(): array
    {
        $states = [];
        foreach (array_flip(self::BASE_STATE) as $bit => $label) {
            if (($this->state & $bit) === $bit) {
                $states[] = $label;
            }
        }
        return $states;
    }

    public function getStateLabels(): string
    {
        $labels = [];

        foreach (array_flip(self::BASE_STATE) as $bit => $label) {
            if (($this->state & $bit) === $bit) {
                $labels[] = $label;
            }
        }

        return implode(', ', $labels);
    }
}
