<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\Media;
use App\Exception\MediaDeletedFileNotRemovedException;
use Doctrine\ORM\EntityManagerInterface;

class AlbumService
{
    private EntityManagerInterface $entityManager;
    private MediaService $mediaService;

    public function __construct(EntityManagerInterface $entityManager, MediaService $mediaService)
    {
        $this->entityManager = $entityManager;
        $this->mediaService = $mediaService;
    }

    /**
     * @throws MediaDeletedFileNotRemovedException
     */
    public function deleteAlbum(Album $album): void
    {
        $cleanupError = null;
        foreach ($this->entityManager->getRepository(Media::class)->findBy(['album' => $album]) as $media) {
            try {
                $this->mediaService->deleteMedia($media);
            } catch (MediaDeletedFileNotRemovedException $e) {
                $cleanupError = $e;
            }
        }
        $this->entityManager->remove($album);
        $this->entityManager->flush();
        if (null !== $cleanupError) {
            throw $cleanupError;
        }
    }
}
