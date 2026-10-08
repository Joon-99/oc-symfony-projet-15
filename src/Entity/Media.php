<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    use IdTrait;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'medias', fetch: 'EAGER')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Album::class, fetch: 'EAGER')]
    private ?Album $album = null;

    #[ORM\Column]
    #[Assert\DisableAutoMapping]
    private string $path;

    #[ORM\Column]
    private string $title;

    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
        detectCorrupted: true, // This forces Symfony to actually decode the image, avoids relying only on mime type to determine image validity
        maxSizeMessage: 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). La taille maximale autorisée est {{ limit }} {{ suffix }}.',
        mimeTypesMessage: 'Le type de fichier n\'est pas valide ({{ type }}). Types autorisés : {{ types }}.',
    )]
    private ?UploadedFile $file = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): static
    {
        $this->album = $album;

        return $this;
    }
}
