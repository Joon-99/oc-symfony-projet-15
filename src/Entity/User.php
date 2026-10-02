<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use App\Validator\Constraints as AppAssert;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\DBAL\Types\Types;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $admin = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    #[AppAssert\ValidEmail]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $password;

    /** @var Collection<int, Media> */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'user')]
    private Collection $medias;

    public function __construct()
    {
        $this->medias = new ArrayCollection();
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    /**
     * @param Collection<int, Media> $medias
     */
    public function setMedias(Collection $medias): void
    {
        $this->medias = $medias;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->admin ? ['ROLE_ADMIN'] : ['ROLE_USER'];
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    #[Override]
    public function eraseCredentials(): void
    {
        // no sensitive data to erase
    }
}
