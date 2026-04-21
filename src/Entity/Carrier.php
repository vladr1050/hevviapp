<?php

namespace App\Entity;

use App\Repository\CarrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarrierRepository::class)]
#[ORM\Index(columns: ['state'])]
#[ORM\Index(columns: ['locale'])]
#[ORM\Index(columns: ['phone'])]
#[ORM\Index(columns: ['phone', 'state'])]
#[ORM\Index(columns: ['first_name', 'last_name'])]
#[ORM\Index(columns: ['legal_name', 'state'])]
#[ORM\Index(columns: ['registration_number'])]
#[ORM\Index(columns: ['registration_number', 'state'])]
class Carrier extends BaseSecurityDBO
{
    #[ORM\Column(length: 255)]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $locale = parent::BASE_LOCALE['English'];

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $state = parent::BASE_STATE['ENABLED'];

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'carrier')]
    private Collection $orders;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    private ?string $legalName = null;

    #[ORM\Column(length: 255)]
    private ?string $registrationNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bankAccountHolder = null;

    /**
     * @var Collection<int, OrderAssignment>
     */
    #[ORM\OneToMany(targetEntity: OrderAssignment::class, mappedBy: 'carrier')]
    private Collection $orderAssignments;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->orderAssignments = new ArrayCollection();
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
            $order->setCarrier($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getCarrier() === $this) {
                $order->setCarrier(null);
            }
        }

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

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function setLegalName(string $legalName): static
    {
        $this->legalName = $legalName;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(string $registrationNumber): static
    {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        if ($iban === null || trim($iban) === '') {
            $this->iban = null;

            return $this;
        }
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $iban));
        $this->iban = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getBankAccountHolder(): ?string
    {
        return $this->bankAccountHolder;
    }

    public function setBankAccountHolder(?string $bankAccountHolder): static
    {
        $this->bankAccountHolder = $bankAccountHolder;

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

    public function getRoles(): array
    {
        return ['ROLE_CARRIER'];
    }

    public function __toString(): string
    {
        return $this->legalName ?? parent::__toString();
    }

    /**
     * @return Collection<int, OrderAssignment>
     */
    public function getOrderAssignments(): Collection
    {
        return $this->orderAssignments;
    }

    public function addOrderAssignment(OrderAssignment $orderAssignment): static
    {
        if (!$this->orderAssignments->contains($orderAssignment)) {
            $this->orderAssignments->add($orderAssignment);
            $orderAssignment->setCarrier($this);
        }

        return $this;
    }

    public function removeOrderAssignment(OrderAssignment $orderAssignment): static
    {
        if ($this->orderAssignments->removeElement($orderAssignment)) {
            // set the owning side to null (unless already changed)
            if ($orderAssignment->getCarrier() === $this) {
                $orderAssignment->setCarrier(null);
            }
        }

        return $this;
    }
}
