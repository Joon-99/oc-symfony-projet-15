<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\UserRepository;
use App\Validator\Constraints as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
    private ?string $description = null;

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

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
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
    public function setMedias(Collection $medias): static
    {
        $this->medias = $medias;

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    #[\Override]
    public function getRoles(): array
    {
        return $this->admin ? ['ROLE_ADMIN'] : ['ROLE_USER'];
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function eraseCredentials(): void
    {
        // no sensitive data to erase
    }
}
