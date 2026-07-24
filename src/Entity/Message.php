<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column]
    private ?\DateTime $date_send = null;

    #[ORM\Column]
    private ?bool $realu = false;

    public function __construct(){
        $this->date_send = new \DateTime();
        $this->realu = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getDateSend(): ?\DateTime
    {
        return $this->date_send;
    }

    public function setDateSend(\DateTime $date_send): static
    {
        $this->date_send = $date_send;

        return $this;
    }

    public function isRealu(): ?bool
    {
        return $this->realu;
    }

    public function setRealu(bool $realu): static
    {
        $this->realu = $realu;

        return $this;
    }
}
