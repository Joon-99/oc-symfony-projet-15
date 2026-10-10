<?php

namespace App\Service;
use App\Exception\MediaDeletedFileNotRemovedException;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

class UserService
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private UserRepository $userRepository;
    private MediaService $mediaService;
    private FileService $fileService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        MediaService $mediaService,
        FileService $fileService,
        LoggerInterface $logger
    )
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->userRepository = $userRepository;
        $this->mediaService = $mediaService;
        $this->fileService = $fileService;
        $this->logger = $logger;
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
     * @return list<User>
     */
    public function getEnabledGuests(): array
    {
        return $this->userRepository->findByEnabledGuest();
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
        foreach ($user->getMedias() as $media) {
            $filePaths[] = $this->mediaService->getMediaFullPath($media);
            $this->entityManager->remove($media);
        }
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        try {
            $this->fileService->deleteFiles($filePaths);
        } catch (IOException $e) {
            $this->logger->error('Failed to delete user media files: ' . $e->getMessage());
            throw new MediaDeletedFileNotRemovedException($e->getMessage(), previous: $e);
        }
    }
}