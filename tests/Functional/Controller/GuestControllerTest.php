<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GuestControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var list<string> */
    private array $filePaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);
    }

    public function testIndexListsGuestsButNotAdministrators(): void
    {
        $guest = $this->createGuest('Guest shown in admin list');
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);

        $this->client->request('GET', '/admin/guest');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', $guest->getName());
        self::assertSelectorTextNotContains('tbody', $admin->getName());
    }

    public function testAdminCanCreateGuest(): void
    {
        $email = 'controller-test-'.uniqid().'@example.com';

        $this->client->request('GET', '/admin/guest/add');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Créer', [
            'guest[name]' => 'Guest created by controller test',
            'guest[email]' => $email,
            'guest[plainPassword]' => 'Strong-Passphrase-729!',
        ]);

        self::assertResponseRedirects('/admin/guest');
        $guest = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($guest);
        self::assertFalse($guest->isAdmin());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)
            ->isPasswordValid($guest, 'Strong-Passphrase-729!'));
    }

    public function testAdminCanDisableAndReenableGuest(): void
    {
        $guest = $this->createGuest('Guest to disable and reenable');
        $guestId = $guest->getId();

        $crawler = $this->client->request('GET', '/admin/guest');
        $form = $crawler->filter('form[action="/admin/guest/disable/'.$guestId.'"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/guest');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->clear();
        $guest = $manager->find(User::class, $guestId);
        self::assertNotNull($guest);
        self::assertFalse($guest->isActive());

        $crawler = $this->client->request('GET', '/admin/guest');
        $form = $crawler->filter('form[action="/admin/guest/enable/'.$guestId.'"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/guest');
        $manager->clear();
        $guest = $manager->find(User::class, $guestId);
        self::assertNotNull($guest);
        self::assertTrue($guest->isActive());
    }

    public function testGuestCannotAccessGuestAdministration(): void
    {
        $guest = $this->createGuest('Guest without admin access');
        $this->client->loginUser($guest);

        $this->client->request('GET', '/admin/guest');

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('mediaCounts')]
    public function testDeletingGuestRemovesMediaAndFilesButKeepsAlbum(int $mediaCount): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $guest = $this->createGuest('Guest deletion test');
        $album = (new Album())->setName('Guest deletion test');
        $manager->persist($album);

        for ($index = 0; $index < $mediaCount; ++$index) {
            $path = tempnam(self::getContainer()->getParameter('media_upload_dir'), 'guest-delete-');
            self::assertNotFalse($path);
            $this->filePaths[] = $path;
            $media = (new Media())
                ->setUser($guest)
                ->setAlbum($album)
                ->setTitle('Deletion test '.$index)
                ->setPath('uploads/'.basename($path));
            $manager->persist($media);
        }
        $manager->flush();
        $guestId = $guest->getId();
        $albumId = $album->getId();
        $manager->clear();

        $crawler = $this->client->request('GET', '/admin/guest');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="/admin/guest/delete/'.$guestId.'"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/guest');

        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->clear();
        self::assertNull($manager->find(User::class, $guestId));
        self::assertSame(0, $manager->getRepository(Media::class)->count(['user' => $guestId]));
        self::assertNotNull($manager->find(Album::class, $albumId));
        foreach ($this->filePaths as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function mediaCounts(): iterable
    {
        yield 'without media' => [0];
        yield 'with two media files' => [2];
    }

    #[DataProvider('rejectedDeleteRequests')]
    public function testRejectedDeleteRequestKeepsGuest(string $method, string $token, int $status): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $guest = $this->createGuest('Guest kept after rejected deletion');
        $manager->flush();
        $guestId = $guest->getId();

        $this->client->request($method, '/admin/guest/delete/'.$guestId, ['_token' => $token]);

        self::assertResponseStatusCodeSame($status);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->clear();
        self::assertNotNull($manager->find(User::class, $guestId));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function rejectedDeleteRequests(): iterable
    {
        yield 'GET request' => ['GET', '', 405];
        yield 'invalid CSRF token' => ['POST', 'invalid', 302];
    }

    private function createGuest(string $name): User
    {
        $guest = (new User())
            ->setName($name)
            ->setEmail('guest-controller-'.uniqid().'@example.com')
            ->setPassword('unused-test-password');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($guest);
        $manager->flush();

        return $guest;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->filePaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
