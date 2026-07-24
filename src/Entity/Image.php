<?php

namespace App\Entity;

use App\Repository\ImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $main_image = false;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $orderImage = 0;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Produit $produit = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isMainImage(): bool
    {
        return $this->main_image;
    }

    public function setMainImage(bool $main_image): static
    {
        $this->main_image = $main_image;

        return $this;
    }

    public function getOrderImage(): int
    {
        return $this->orderImage;
    }

    public function setOrderImage(int $orderImage): static
    {
        $this->orderImage = $orderImage;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }
}
