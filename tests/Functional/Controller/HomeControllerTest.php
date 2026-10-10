<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testActiveGuestProfileIsPubliclyVisible(): void
    {
        $guest = $this->createGuest('Public guest profile test');

        $this->client->request('GET', '/guest/'.$guest->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', $guest->getName());
    }

    public function testAdministratorProfileIsNotPubliclyViewable(): void
    {
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);

        $this->client->request('GET', '/guest/'.$admin->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testDisabledGuestProfileIsHiddenFromAnonymousVisitors(): void
    {
        $guest = $this->disableGuest($this->createGuest('Disabled guest profile test'));

        $this->client->request('GET', '/guest/'.$guest->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testDisabledGuestProfileRemainsVisibleToAdmin(): void
    {
        $guest = $this->disableGuest($this->createGuest('Disabled guest visible to admin test'));
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/guest/'.$guest->getId());

        self::assertResponseIsSuccessful();
    }

    private function createGuest(string $name): User
    {
        $guest = (new User())
            ->setName($name)
            ->setEmail('home-controller-'.uniqid().'@example.com')
            ->setPassword('unused-test-password');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($guest);
        $manager->flush();

        return $guest;
    }

    private function disableGuest(User $guest): User
    {
        $guest->setDisabledAt(new \DateTimeImmutable());
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        return $guest;
    }
}
