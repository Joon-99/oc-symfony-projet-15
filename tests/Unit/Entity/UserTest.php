<?php

namespace App\Tests\Unit;

use App\Entity\Media;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultRole(): void
    {
        $user = new User();
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testAdminRole(): void
    {
        $user = new User();
        $this->assertFalse($user->isAdmin());
        $user->setAdmin(true);
        $this->assertTrue($user->isAdmin());
        $this->assertSame(['ROLE_ADMIN'], $user->getRoles());
        $user->setAdmin(false);
        $this->assertFalse($user->isAdmin());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testUserIdentifier(): void
    {
        $email = 'test@example.com';
        $user = new User();
        $user->setEmail($email);
        $this->assertSame($email, $user->getUserIdentifier());
    }

    public function testInitialMedias(): void
    {
        $user = new User();
        $this->assertTrue($user->getMedias()->isEmpty());
    }

    public function testAccessors(): void
    {
        $testMedia = (new Media())->setPath('path/to/media')->setTitle('Media Title');
        $userData = [
            'email' => 'test@example.com',
            'password' => 'password',
            'name' => 'Test User',
            'description' => 'Test description',
            'medias' => new ArrayCollection([$testMedia]),
        ];
        $user = new User();

        $this->assertNull($user->getId());

        $user->setEmail($userData['email']);
        $this->assertSame($userData['email'], $user->getEmail());

        $user->setPassword($userData['password']);
        $this->assertSame($userData['password'], $user->getPassword());

        $user->setName($userData['name']);
        $this->assertSame($userData['name'], $user->getName());

        $user->setDescription($userData['description']);
        $this->assertSame($userData['description'], $user->getDescription());

        $user->setMedias($userData['medias']);
        $this->assertSame($userData['medias'], $user->getMedias());
    }
}
