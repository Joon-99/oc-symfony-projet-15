<?php

namespace App\Service;

use App\Entity\Media;
use App\Exception\MediaDeletedFileNotRemovedException;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Path;

class MediaService
{
    private EntityManagerInterface $entityManager;
    private FileService $fileService;
    private string $mediaUploadsDir;
    private LoggerInterface $logger;
    private CacheManager $cacheManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        FileService $fileService,
        #[Autowire('%media_upload_dir%')] string $mediaUploadsDir,
        LoggerInterface $logger,
        CacheManager $cacheManager,
    ) {
        $this->entityManager = $entityManager;
        $this->fileService = $fileService;
        $this->mediaUploadsDir = $mediaUploadsDir;
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
    }

    public function getMediaFullPath(Media $media): string
    {
        return Path::join($this->mediaUploadsDir, basename($media->getPath()));
    }

    /**
     * @throws MediaDeletedFileNotRemovedException
     */
    public function deleteMedia(Media $media): void
    {
        $this->entityManager->remove($media);
        $this->entityManager->flush();
        try {
            $this->cacheManager->remove($media->getPath());
            $this->fileService->deleteFile($this->getMediaFullPath($media));
        } catch (IOException $e) {
            $this->logger->error(MediaDeletedFileNotRemovedException::DEFAULT_MESSAGE.$e->getMessage());
            throw new MediaDeletedFileNotRemovedException($e->getMessage(), previous: $e);
        }
    }
}
