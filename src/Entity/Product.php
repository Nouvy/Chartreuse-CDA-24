<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']]
)]
#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['product:read', 'product:write'])]
    private ?int $ht_price = null;

    #[ORM\Column]
    #[Groups(['product:read', 'product:write'])]
    private ?float $vat_rate = null;

    #[ORM\Column]
    #[Groups(['product:read', 'product:write'])]
    private ?int $stock = null;

    #[ORM\ManyToMany(targetEntity: Order::class, inversedBy: 'products')]
    #[Groups(['product:read'])]
    private Collection $orders;

    #[ORM\ManyToMany(targetEntity: Cart::class, inversedBy: 'products')]
    #[Groups(['product:read'])]
    private Collection $carts;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->carts = new ArrayCollection();
    }

    #[Groups(['order:read'])]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(['order:read'])]
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    #[Groups(['order:read'])]
    public function getHtPrice(): ?int
    {
        return $this->ht_price;
    }

    public function setHtPrice(int $ht_price): static
    {
        $this->ht_price = $ht_price;
        return $this;
    }

    public function getVatRate(): ?float
    {
        return $this->vat_rate;
    }

    public function setVatRate(float $vat_rate): static
    {
        $this->vat_rate = $vat_rate;
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;
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
        }
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        $this->orders->removeElement($order);
        return $this;
    }

    /**
     * @return Collection<int, Cart>
     */
    public function getCarts(): Collection
    {
        return $this->carts;
    }

    public function addCart(Cart $cart): static
    {
        if (!$this->carts->contains($cart)) {
            $this->carts->add($cart);
        }
        return $this;
    }

    public function removeCart(Cart $cart): static
    {
        $this->carts->removeElement($cart);
        return $this;
    }
}
