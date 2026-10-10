<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
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

    /** Protects against active guest profiles becoming inaccessible to visitors. */
    public function testActiveGuestProfileIsPubliclyVisible(): void
    {
        $guest = $this->createGuest('Public guest profile test');

        $this->client->request('GET', '/guest/'.$guest->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', $guest->getName());
    }

    /** Protects against administrator accounts being exposed as public guest profiles. */
    public function testAdministratorProfileIsNotPubliclyViewable(): void
    {
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);

        $this->client->request('GET', '/guest/'.$admin->getId());

        self::assertResponseStatusCodeSame(404);
    }

    /** Protects against disabled guest profiles remaining visible to anonymous visitors. */
    public function testDisabledGuestProfileIsHiddenFromAnonymousVisitors(): void
    {
        $guest = $this->disableGuest($this->createGuest('Disabled guest profile test'));

        $this->client->request('GET', '/guest/'.$guest->getId());

        self::assertResponseStatusCodeSame(404);
    }

    /** Protects admins from losing access to disabled guest profiles they manage. */
    public function testDisabledGuestProfileRemainsVisibleToAdmin(): void
    {
        $guest = $this->disableGuest($this->createGuest('Disabled guest visible to admin test'));
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/guest/'.$guest->getId());

        self::assertResponseIsSuccessful();
    }

    public function testAlbumHidesDisabledGuestPhotosAndRestoresThemWhenEnabled(): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $guest = $this->createGuest('Guest with album photo');
        $otherGuest = $this->createGuest('Other active guest with album photo');
        $album = (new Album())->setName('Guest visibility test album');
        $manager->persist($album);

        foreach ([
            'Administrator photo' => $admin,
            'Guest photo to hide' => $guest,
            'Other active guest photo' => $otherGuest,
            'Unowned photo' => null,
        ] as $title => $owner) {
            $manager->persist((new Media())
                ->setAlbum($album)
                ->setUser($owner)
                ->setTitle($title)
                ->setPath('images/logo.png'));
        }
        $manager->flush();
        $albumId = $album->getId();
        $guestId = $guest->getId();

        $this->client->request('GET', '/portfolio/'.$albumId);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(4, '.media-title');

        $guest = self::getContainer()->get(UserRepository::class)->find($guestId);
        self::assertNotNull($guest);
        self::getContainer()->get(UserService::class)->disableUser($guest);

        $this->client->request('GET', '/portfolio/'.$albumId);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.media-title');
        self::assertSelectorTextNotContains('main', 'Guest photo to hide');
        foreach (['Administrator photo', 'Other active guest photo', 'Unowned photo'] as $title) {
            self::assertSelectorTextContains('main', $title);
        }

        $guest = self::getContainer()->get(UserRepository::class)->find($guestId);
        self::assertNotNull($guest);
        self::getContainer()->get(UserService::class)->enableUser($guest);

        $this->client->request('GET', '/portfolio/'.$albumId);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(4, '.media-title');
        self::assertSelectorTextContains('main', 'Guest photo to hide');
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
