<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Model\Binary;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Exception\IOException;

class AlbumControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var list<string> */
    private array $filePaths = [];

    /** @var list<string> */
    private array $cachePaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);
    }

    /** Protects against the admin album list omitting persisted albums. */
    public function testIndexDisplaysAlbums(): void
    {
        $album = (new Album())->setName('Album controller index test');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($album);
        $manager->flush();

        $this->client->request('GET', '/admin/album');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'Album controller index test');
    }

    /** Protects against album creation failing to persist submitted data. */
    public function testAddCreatesAlbum(): void
    {
        $name = 'Album controller add test';
        $this->client->request('GET', '/admin/album/add');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Ajouter', ['album[name]' => $name]);

        self::assertResponseRedirects('/admin/album');
        $album = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Album::class)
            ->findOneBy(['name' => $name]);
        self::assertNotNull($album);
    }

    /** Protects against album edits failing to persist the renamed value. */
    public function testUpdateChangesAlbumName(): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $album = (new Album())->setName('Album before update');
        $manager->persist($album);
        $manager->flush();
        $albumId = $album->getId();

        $this->client->request('GET', '/admin/album/update/'.$albumId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="album[name]"][value="Album before update"]');

        $this->client->submitForm('Modifier', ['album[name]' => 'Album after update']);

        self::assertResponseRedirects('/admin/album');
        $manager->clear();
        $updatedAlbum = $manager->find(Album::class, $albumId);
        self::assertNotNull($updatedAlbum);
        self::assertSame('Album after update', $updatedAlbum->getName());
    }

    /** Protects against album deletion leaving the record in storage. */
    public function testDeleteRemovesAlbum(): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $album = (new Album())->setName('Album controller delete test');
        $manager->persist($album);
        $manager->flush();
        $albumId = $album->getId();

        $crawler = $this->client->request('GET', '/admin/album');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action="/admin/album/delete/'.$albumId.'"]')->form());

        self::assertResponseRedirects('/admin/album');
        $manager->clear();
        self::assertNull($manager->find(Album::class, $albumId));
    }

    #[DataProvider('fileCleanupResults')]
    public function testDeletingPopulatedAlbumRemovesItsMediaFilesAndCacheOnly(bool $cleanupFails): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $album = (new Album())->setName('Populated album');
        $otherAlbum = (new Album())->setName('Album kept');
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['admin' => true]);
        self::assertNotNull($admin);
        $guest = (new User())
            ->setName('Disabled album contributor')
            ->setEmail('album-delete-'.uniqid().'@example.com')
            ->setPassword('unused')
            ->setDisabledAt(new \DateTimeImmutable());
        $manager->persist($album);
        $manager->persist($otherAlbum);
        $manager->persist($guest);
        $mediaIds = [];
        foreach ([$admin, $guest, null] as $owner) {
            $media = $this->createMedia($album, $owner);
            $manager->flush();
            $mediaIds[] = $media->getId();
        }
        $unrelated = $this->createMedia($otherAlbum, $guest);
        $manager->flush();
        $albumId = $album->getId();
        $otherAlbumId = $otherAlbum->getId();
        $guestId = $guest->getId();
        $unrelatedId = $unrelated->getId();
        $unrelatedPath = $unrelated->getPath();
        $manager->clear();

        if ($cleanupFails) {
            $this->client->disableReboot();
            $fileService = $this->createMock(FileService::class);
            $originalFileService = new FileService();
            $failedPath = $this->filePaths[0];
            $fileService->expects(self::exactly(3))->method('deleteFile')->willReturnCallback(
                static function (string $path) use ($failedPath, $originalFileService): void {
                    if ($path === $failedPath) {
                        throw new IOException('Simulated file deletion failure');
                    }
                    $originalFileService->deleteFile($path);
                }
            );
            self::getContainer()->set(FileService::class, $fileService);
        }
        $crawler = $this->client->request('GET', '/admin/album');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action="/admin/album/delete/'.$albumId.'"]')->form());

        self::assertResponseRedirects('/admin/album');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        if ($cleanupFails) {
            self::assertSelectorTextContains('[role="alert"]', "L'album et ses médias ont été supprimés, mais certains fichiers n'ont pas pu être supprimés.");
            self::assertFileExists($this->filePaths[0]);
        } else {
            self::assertSelectorNotExists('[role="alert"]');
            self::assertFileDoesNotExist($this->filePaths[0]);
        }
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->clear();
        self::assertNull($manager->find(Album::class, $albumId));
        foreach ($mediaIds as $mediaId) {
            self::assertNull($manager->find(Media::class, $mediaId));
        }
        $cache = self::getContainer()->get(CacheManager::class);
        foreach (array_slice($this->filePaths, 1, 2) as $path) {
            self::assertFileDoesNotExist($path);
        }
        foreach (array_slice($this->cachePaths, 0, 3) as $path) {
            self::assertFalse($cache->isStored($path, 'compressed'));
        }
        self::assertNotNull($manager->find(Album::class, $otherAlbumId));
        self::assertNotNull($manager->find(User::class, $guestId));
        self::assertNotNull($manager->find(Media::class, $unrelatedId));
        self::assertFileExists($this->filePaths[3]);
        self::assertTrue($cache->isStored($unrelatedPath, 'compressed'));
    }

    /** @return iterable<string, array{bool}> */
    public static function fileCleanupResults(): iterable
    {
        yield 'all files removed' => [false];
        yield 'one file cannot be removed' => [true];
    }

    private function createMedia(Album $album, ?User $owner): Media
    {
        $path = tempnam(self::getContainer()->getParameter('media_upload_dir'), 'album-delete-');
        self::assertNotFalse($path);
        $this->filePaths[] = $path;
        $media = (new Media())
            ->setAlbum($album)
            ->setUser($owner)
            ->setTitle('Album deletion photo')
            ->setPath('uploads/'.basename($path));
        self::getContainer()->get(EntityManagerInterface::class)->persist($media);
        $this->cachePaths[] = $media->getPath();
        $cache = self::getContainer()->get(CacheManager::class);
        $cache->store(new Binary('cached image', 'image/webp', 'webp'), $media->getPath(), 'compressed');
        self::assertTrue($cache->isStored($media->getPath(), 'compressed'));

        return $media;
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('rejectedDeleteRequests')]
    public function testRejectedDeleteRequestKeepsAlbum(string $method, array $parameters, int $status): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $album = (new Album())->setName('Album kept after rejected deletion');
        $manager->persist($album);
        $media = $this->createMedia($album, null);
        $manager->flush();
        $albumId = $album->getId();
        $mediaId = $media->getId();
        $cachePath = $media->getPath();

        $this->client->request('GET', '/admin/album');
        self::assertResponseIsSuccessful();
        $this->client->request($method, '/admin/album/delete/'.$albumId, $parameters);

        self::assertResponseStatusCodeSame($status);
        if ('POST' === $method) {
            self::assertResponseRedirects('http://localhost/login');
        }
        $manager->clear();
        self::assertNotNull($manager->find(Album::class, $albumId));
        self::assertNotNull($manager->find(Media::class, $mediaId));
        self::assertFileExists($this->filePaths[0]);
        self::assertTrue(self::getContainer()->get(CacheManager::class)->isStored($cachePath, 'compressed'));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, int}>
     */
    public static function rejectedDeleteRequests(): iterable
    {
        yield 'GET' => ['GET', [], 405];
        yield 'missing token' => ['POST', [], 302];
        yield 'invalid token' => ['POST', ['_token' => 'invalid'], 302];
    }

    protected function tearDown(): void
    {
        if ($this->cachePaths) {
            self::getContainer()->get(CacheManager::class)->remove($this->cachePaths);
        }
        parent::tearDown();
        foreach ($this->filePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
