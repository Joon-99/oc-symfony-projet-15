<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\UserRepository;
use App\Validator\Constraints as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $admin = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    #[AppAssert\ValidEmail]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\DisableAutoMapping]
    private string $password;

    #[AppAssert\ValidPassword(groups: ['createGuest'])]
    private ?string $plainPassword = null;

    /** @var Collection<int, Media> */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'user')]
    private Collection $medias;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $disabledAt = null;

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function isActive(): bool
    {
        return null === $this->disabledAt;
    }

    public function getDisabledAt(): ?\DateTimeImmutable
    {
        return $this->disabledAt;
    }

    public function setDisabledAt(?\DateTimeImmutable $disabledAt): static
    {
        $this->disabledAt = $disabledAt;

        return $this;
    }

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
        $this->plainPassword = null;

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

    #[\Override]
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        $roles = $this->getRoles();
        $otherRoles = $user->getRoles();

        sort($roles);
        sort($otherRoles);

        return $this->getUserIdentifier() === $user->getUserIdentifier()
            && $this->getPassword() === $user->getPassword()
            && $roles === $otherRoles
            && $this->isActive() === $user->isActive();
    }

    /**
     * @codeCoverageIgnore
     */
    #[\Deprecated(since: 'symfony/security-bundle 7.3')]
    #[\Override]
    public function eraseCredentials(): void
    {
        // no sensitive data to erase
    }
}
