<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
#[ORM\Index(name: 'idx_order_pickup_address', columns: ['pickup_address'])]
#[ORM\Index(name: 'idx_order_dropout_address', columns: ['dropout_address'])]
class Order extends BaseUUID
{
    public const array STATUS = [
        'DRAFT' => 1,
        'OFFERED' => 2,
        'ACCEPTED' => 3,
        'INVOICED' => 4,
        'PAID' => 5,
        'ASSIGNED' => 6,
        'AWAITING_PICKUP' => 7,
        'PICKUP_DONE' => 8,
        'IN_TRANSIT' => 9,
        'DELIVERED' => 10,
        'CANCELLED' => -1
    ];

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Carrier $carrier = null;

    /**
     * @var Collection<int, Cargo>
     */
    #[ORM\OneToMany(targetEntity: Cargo::class, mappedBy: 'relatedOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cargo;

    #[ORM\Column]
    private ?int $status = self::STATUS['DRAFT'];

    #[ORM\Column(length: 255)]
    private ?string $pickupAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $dropoutAddress = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $dropoutLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $dropoutLongitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * @var Collection<int, OrderHistory>
     */
    #[ORM\OneToMany(targetEntity: OrderHistory::class, mappedBy: 'relatedOrder', cascade: ['persist'], orphanRemoval: true)]
    private Collection $histories;

    /**
     * @var Collection<int, OrderOffer>
     */
    #[ORM\OneToMany(targetEntity: OrderOffer::class, mappedBy: 'relatedOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $offers;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $currency = null;

    /**
     * @var Collection<int, OrderAssignment>
     */
    #[ORM\OneToMany(targetEntity: OrderAssignment::class, mappedBy: 'relatedOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orderAssignments;

    public function __construct()
    {
        $this->cargo = new ArrayCollection();
        $this->histories = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->orderAssignments = new ArrayCollection();
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): static
    {
        $this->carrier = $carrier;

        return $this;
    }

    /**
     * @return Collection<int, Cargo>
     */
    public function getCargo(): Collection
    {
        return $this->cargo;
    }

    public function addCargo(Cargo $cargo): static
    {
        if (!$this->cargo->contains($cargo)) {
            $this->cargo->add($cargo);
            $cargo->setRelatedOrder($this);
        }

        return $this;
    }

    public function removeCargo(Cargo $cargo): static
    {
        if ($this->cargo->removeElement($cargo)) {
            // set the owning side to null (unless already changed)
            if ($cargo->getRelatedOrder() === $this) {
                $cargo->setRelatedOrder(null);
            }
        }

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPickupAddress(): ?string
    {
        return $this->pickupAddress;
    }

    public function setPickupAddress(string $pickupAddress): static
    {
        $this->pickupAddress = $pickupAddress;

        return $this;
    }

    public function getDropoutAddress(): ?string
    {
        return $this->dropoutAddress;
    }

    public function setDropoutAddress(string $dropoutAddress): static
    {
        $this->dropoutAddress = $dropoutAddress;

        return $this;
    }

    public function getDropoutLatitude(): ?string
    {
        return $this->dropoutLatitude;
    }

    public function setDropoutLatitude(?string $dropoutLatitude): static
    {
        $this->dropoutLatitude = $dropoutLatitude;

        return $this;
    }

    public function getDropoutLongitude(): ?string
    {
        return $this->dropoutLongitude;
    }

    public function setDropoutLongitude(?string $dropoutLongitude): static
    {
        $this->dropoutLongitude = $dropoutLongitude;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function __toString(): string
    {
        $id = $this->getId() ? $this->getId()->toRfc4122() : 'new';
        return sprintf('Order #%s', substr($id, 0, 8));
    }

    /**
     * @return Collection<int, OrderHistory>
     */
    public function getHistories(): Collection
    {
        return $this->histories;
    }

    public function addHistory(OrderHistory $history): static
    {
        if (!$this->histories->contains($history)) {
            $this->histories->add($history);
            $history->setRelatedOrder($this);
        }

        return $this;
    }

    public function removeHistory(OrderHistory $history): static
    {
        if ($this->histories->removeElement($history)) {
            // set the owning side to null (unless already changed)
            if ($history->getRelatedOrder() === $this) {
                $history->setRelatedOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderOffer>
     */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(OrderOffer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setRelatedOrder($this);
        }

        return $this;
    }

    public function removeOffer(OrderOffer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            // set the owning side to null (unless already changed)
            if ($offer->getRelatedOrder() === $this) {
                $offer->setRelatedOrder(null);
            }
        }

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Получает последний OrderOffer по дате создания
     */
    public function getLatestOffer(): ?OrderOffer
    {
        if ($this->offers->isEmpty()) {
            return null;
        }

        $offersArray = $this->offers->toArray();
        
        usort($offersArray, function (OrderOffer $a, OrderOffer $b) {
            $dateA = $a->getCreatedAt();
            $dateB = $b->getCreatedAt();
            
            if ($dateA === null && $dateB === null) {
                return 0;
            }
            if ($dateA === null) {
                return 1;
            }
            if ($dateB === null) {
                return -1;
            }
            
            return $dateB <=> $dateA;
        });

        return $offersArray[0];
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
            $orderAssignment->setRelatedOrder($this);
        }

        return $this;
    }

    public function removeOrderAssignment(OrderAssignment $orderAssignment): static
    {
        if ($this->orderAssignments->removeElement($orderAssignment)) {
            // set the owning side to null (unless already changed)
            if ($orderAssignment->getRelatedOrder() === $this) {
                $orderAssignment->setRelatedOrder(null);
            }
        }

        return $this;
    }
}
