<?php

namespace App\Tests\Unit;

use App\Entity\User;
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


}