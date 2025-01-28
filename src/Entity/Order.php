<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['order:read']],
    denormalizationContext: ['groups' => ['order:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
    ]
)]
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['order:read', 'order:write'])]
    private ?\DateTimeInterface $orderDate = null;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private ?bool $status = null;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private ?int $quantity = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read'])]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'order_product', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read'])]
    private ?Payment $payment = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'orders')]
    #[Groups(['order:read'])]
    private Collection $products;

    #[Groups(['order:write'])]
    private array $product_ids = [];

    #[Groups(['order:write'])]
    private ?int $user_id = null;

    #[Groups(['order:write'])]
    private ?int $payment_id = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->orderDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderDate(): ?\DateTimeInterface
    {
        return $this->orderDate;
    }

    public function setOrderDate(\DateTimeInterface $orderDate): static
    {
        $this->orderDate = $orderDate;

        return $this;
    }

    public function isStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addOrder($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeOrder($this);
        }

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(?int $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getPaymentId(): ?int
    {
        return $this->payment_id;
    }

    public function setPaymentId(?int $payment_id): static
    {
        $this->payment_id = $payment_id;

        return $this;
    }

    public function getProductIds(): array
    {
        return $this->product_ids;
    }

    public function setProductIds(array $product_ids): static
    {
        $this->product_ids = $product_ids;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(PrePersistEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();

        // Configurer l'utilisateur
        if (null !== $this->user_id) {
            $user = $entityManager->getRepository(User::class)->find($this->user_id);
            if ($user) {
                $this->setUser($user);
            }
        }

        // Configurer le paiement
        if (null !== $this->payment_id) {
            $payment = $entityManager->getRepository(Payment::class)->find($this->payment_id);
            if ($payment) {
                $this->setPayment($payment);
            }
        }

        // Configurer les produits
        if (!empty($this->product_ids)) {
            foreach ($this->product_ids as $productId) {
                $product = $entityManager->getRepository(Product::class)->find($productId);
                if ($product) {
                    $this->addProduct($product);
                }
            }
            $this->setQuantity(count($this->product_ids));
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(PreUpdateEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $changeSet = $args->getEntityChangeSet();

        // Mettre à jour l'utilisateur si user_id a changé
        if (null !== $this->user_id) {
            $user = $entityManager->getRepository(User::class)->find($this->user_id);
            if ($user) {
                $this->setUser($user);
            }
        }

        // Mettre à jour le paiement si payment_id a changé
        if (null !== $this->payment_id) {
            $payment = $entityManager->getRepository(Payment::class)->find($this->payment_id);
            if ($payment) {
                $this->setPayment($payment);
            }
        }

        // Mettre à jour les produits si product_ids a changé
        if (!empty($this->product_ids)) {
            // Supprimer les anciens produits
            foreach ($this->products as $product) {
                $this->removeProduct($product);
            }

            // Ajouter les nouveaux produits
            foreach ($this->product_ids as $productId) {
                $product = $entityManager->getRepository(Product::class)->find($productId);
                if ($product) {
                    $this->addProduct($product);
                }
            }
            $this->setQuantity(count($this->product_ids));
        }
    }
}
