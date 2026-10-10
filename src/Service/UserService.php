<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\MediaDeletedFileNotRemovedException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private UserRepository $userRepository;
    private MediaService $mediaService;
    private FileService $fileService;
    private LoggerInterface $logger;
    private CacheManager $cacheManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        MediaService $mediaService,
        FileService $fileService,
        LoggerInterface $logger,
        CacheManager $cacheManager,
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->userRepository = $userRepository;
        $this->mediaService = $mediaService;
        $this->fileService = $fileService;
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
    }

    public function disableUser(User $user): bool
    {
        if ($user->isActive()) {
            $user->setDisabledAt(new \DateTimeImmutable());
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return true;
        }

        return false;
    }

    public function enableUser(User $user): bool
    {
        if (!$user->isActive()) {
            $user->setDisabledAt(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return true;
        }

        return false;
    }

    /**
     * @return list<array{user: User, mediaCount: int}>
     */
    public function getEnabledGuestsAndMediaCount(): array
    {
        return $this->userRepository->findByEnabledGuestWithMediaCount();
    }

    public function createGuestUser(User $transientUser): void
    {
        $plainPassword = $transientUser->getPlainPassword();
        if (!$plainPassword) {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }
        $hashedPassword = $this->passwordHasher->hashPassword($transientUser, $plainPassword);
        $transientUser->setPassword($hashedPassword);
        $transientUser->setAdmin(false);
        $this->entityManager->persist($transientUser);
        $this->entityManager->flush();
    }

    public function deleteUser(User $user): void
    {
        $filePaths = [];
        $cachePaths = [];
        foreach ($user->getMedias() as $media) {
            $filePaths[] = $this->mediaService->getMediaFullPath($media);
            $cachePaths[] = $media->getPath();
            $this->entityManager->remove($media);
        }
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        try {
            if ($cachePaths) {
                $this->cacheManager->remove($cachePaths);
            }
            $this->fileService->deleteFiles($filePaths);
        } catch (IOException $e) {
            $this->logger->error('Failed to delete user media files: '.$e->getMessage());
            throw new MediaDeletedFileNotRemovedException($e->getMessage(), previous: $e);
        }
    }
}
