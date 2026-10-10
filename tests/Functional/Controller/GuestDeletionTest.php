<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GuestDeletionTest extends WebTestCase
{
    /** @var list<string> */
    private array $filePaths = [];

    #[DataProvider('mediaCounts')]
    public function testDeletingGuestRemovesMediaAndFilesButKeepsAlbum(int $mediaCount): void
    {
        $client = static::createClient();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $guest = $this->createGuest($manager);
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

        $crawler = $client->request('GET', '/admin/guest');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="/admin/guest/delete/'.$guestId.'"]')->form();
        $client->submit($form);
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

    #[DataProvider('rejectedRequests')]
    public function testRejectedRequestKeepsUser(string $method, string $token, int $status): void
    {
        $client = static::createClient();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $guest = $this->createGuest($manager);
        $manager->flush();
        $guestId = $guest->getId();

        $client->request($method, '/admin/guest/delete/'.$guestId, ['_token' => $token]);
        self::assertResponseStatusCodeSame($status);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->clear();
        self::assertNotNull($manager->find(User::class, $guestId));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function rejectedRequests(): iterable
    {
        yield 'GET' => ['GET', '', 405];
        yield 'invalid token' => ['POST', 'invalid', 302];
    }

    private function createGuest(EntityManagerInterface $manager): User
    {
        $guest = (new User())
            ->setName('Deletion test guest')
            ->setEmail('delete-test-'.uniqid().'@example.com')
            ->setPassword('unused-test-password');
        $manager->persist($guest);

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
