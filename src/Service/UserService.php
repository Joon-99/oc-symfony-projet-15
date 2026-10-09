<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
}