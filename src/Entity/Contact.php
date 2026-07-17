<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Category;
use App\Enum\Sentiment;
use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contacts')]
#[ORM\HasLifecycleCallbacks]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $email = '';

    #[ORM\Column(length: 32)]
    private string $phone = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $comment = '';

    #[ORM\Column(length: 32, enumType: Sentiment::class)]
    private Sentiment $sentiment = Sentiment::Unknown;

    #[ORM\Column(length: 32, enumType: Category::class)]
    private Category $category = Category::Other;

    #[ORM\Column(type: Types::TEXT)]
    private string $autoReply = '';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $clientIp = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getSentiment(): Sentiment
    {
        return $this->sentiment;
    }

    public function setSentiment(Sentiment $sentiment): self
    {
        $this->sentiment = $sentiment;

        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getAutoReply(): string
    {
        return $this->autoReply;
    }

    public function setAutoReply(string $autoReply): self
    {
        $this->autoReply = $autoReply;

        return $this;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function setClientIp(?string $clientIp): self
    {
        $this->clientIp = $clientIp;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
