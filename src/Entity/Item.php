<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ItemRepository;
use App\State\ItemDataProvider;
use App\State\ItemDataProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: ItemDataProvider::class,
            normalizationContext: ['groups' => ['item:read']]
        ),
        new Post(),
        new Get(
            provider: ItemDataProvider::class
        ),
        new Delete(
            uriTemplate: '/items/{id}',
            controller: 'App\\Controller\\ItemController::deleteItem',
        ),
        new Post(
            uriTemplate: '/items/upload',
            controller: 'App\\Controller\\ItemController::upload',
            deserialize: false,
            validate: false,
        ),
        new Get(
            uriTemplate: '/items/{id}/file',
            controller: 'App\\Controller\\ItemController::download',
            serialize: false,
        ),
        new GetCollection(
            uriTemplate: '/items/my',
            controller: 'App\\Controller\\ItemController::myItems',
        ),
        new Put(
            uriTemplate: '/items/{id}/update',
            controller: 'App\\Controller\\ItemController::updateTextFile',
            deserialize: false,
            validate: false,
        ),
        new Get(
            uriTemplate: '/items/{id}/get_shared_url',
            controller: 'App\\Controller\\ItemController::getSharedUrl',
        )
    ],
    normalizationContext: ['groups' => ['item:read']],
    denormalizationContext: ['groups' => ['item:write']]
)]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['item:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Groups(['item:read', 'item:write'])]
    private ?string $location = null;

    #[ORM\Column(length: 255)]
    #[Groups(['item:read'])]
    private ?string $filename = null;

    #[ORM\Column(length: 100)]
    #[Groups(['item:read'])]
    private ?string $contentType = null;

    #[ORM\Column]
    #[Groups(['item:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['item:read', 'item:write'])]
    private bool $isShared = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getIsShared(): bool
    {
        return $this->isShared;
    }

    public function isShared(): bool
    {
        return $this->isShared;
    }

    public function setIsShared(bool $isShared): static
    {
        $this->isShared = $isShared;

        return $this;
    }
}