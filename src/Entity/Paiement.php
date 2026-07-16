<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $amountincents = null;

    #[ORM\Column]
    private ?\DateTime $payment_date = null;

    #[ORM\Column(length: 30)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = null;

    #[ORM\Column(length: 255)]
    private ?string $reference_transaction = null;

    #[ORM\OneToOne(inversedBy: 'paiement', cascade: ['persist', 'remove'])]
    private ?Commande $commande = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmountincents(): ?int
    {
        return $this->amountincents;
    }

    public function setAmountincents(int $amountincents): static
    {
        $this->amountincents = $amountincents;

        return $this;
    }

    public function getPaymentDate(): ?\DateTime
    {
        return $this->payment_date;
    }

    public function setPaymentDate(\DateTime $payment_date): static
    {
        $this->payment_date = $payment_date;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getReferenceTransaction(): ?string
    {
        return $this->reference_transaction;
    }

    public function setReferenceTransaction(string $reference_transaction): static
    {
        $this->reference_transaction = $reference_transaction;

        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }
}
