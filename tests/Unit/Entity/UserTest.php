<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Media;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /** Protects against users losing the default non-admin role. */
    public function testDefaultRole(): void
    {
        $user = new User();
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    /** Protects against admin status and its derived roles becoming inconsistent. */
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

    /** Protects against login identifiers differing from the user's email. */
    public function testUserIdentifier(): void
    {
        $email = 'test@example.com';
        $user = new User();
        $user->setEmail($email);
        $this->assertSame($email, $user->getUserIdentifier());
    }

    /** Protects against new users receiving an unexpected media collection. */
    public function testInitialMedias(): void
    {
        $user = new User();
        $this->assertTrue($user->getMedias()->isEmpty());
    }

    /** Protects against user accessors returning or storing the wrong values. */
    public function testAccessors(): void
    {
        $testMedia = (new Media())->setPath('path/to/media')->setTitle('Media Title');
        $medias = new ArrayCollection([$testMedia]);
        $user = new User();

        $this->assertNull($user->getId());

        $user->setEmail('test@example.com');
        $this->assertSame('test@example.com', $user->getEmail());

        $user->setPassword('password');
        $this->assertSame('password', $user->getPassword());

        $user->setName('Test User');
        $this->assertSame('Test User', $user->getName());

        $user->setDescription('Test description');
        $this->assertSame('Test description', $user->getDescription());

        $user->setMedias($medias);
        $this->assertSame($medias, $user->getMedias());
    }
}
