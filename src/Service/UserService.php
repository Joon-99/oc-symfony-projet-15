<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->userRepository = $userRepository;
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

    /**
     * @return list<User>
     */
    public function getEnabledGuests(): array
    {
        return $this->userRepository->findByEnabledGuest();
    }
}