<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Model\Binary;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GuestMediaTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $guest;

    /** @var list<string> */
    private array $filePaths = [];

    /** @var list<string> */
    private array $cachePaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->guest = $this->createUser('Media test guest');
    }

    /** Protects media management from anonymous access or unintended data changes. */
    #[DataProvider('anonymousRequests')]
    public function testAnonymousVisitorCannotAccessMediaManagement(string $method, string $url): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $mediaCount = $manager->getRepository(Media::class)->count([]);

        $this->client->request($method, $url, [
            'media' => ['title' => 'Anonymous upload'],
        ], [
            'media' => ['file' => $this->uploadedImage()],
        ]);

        self::assertResponseRedirects('http://localhost/login');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($mediaCount, $manager->getRepository(Media::class)->count([]));
    }

    /** @return iterable<string, array{string, string}> */
    public static function anonymousRequests(): iterable
    {
        yield 'list media' => ['GET', '/admin/media'];
        yield 'open upload form' => ['GET', '/admin/media/add'];
        yield 'submit upload' => ['POST', '/admin/media/add'];
    }

    /** Protects guest uploads from assigning another owner or changing shared album data. */
    public function testGuestCanUploadAnImageOnlyForThemselves(): void
    {
        $this->client->loginUser($this->guest);
        $this->client->request('GET', '/admin/media/add');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#media_title');
        self::assertSelectorExists('#media_file');
        self::assertSelectorNotExists('#media_user');
        self::assertSelectorNotExists('#media_album');

        $this->client->submitForm('Ajouter', [
            'media[title]' => 'Guest uploaded image',
            'media[file]' => self::$kernel->getProjectDir().'/tests/Resources/test-upload-media.jpg',
        ]);

        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $media = $manager->getRepository(Media::class)->findOneBy(['title' => 'Guest uploaded image']);
        self::assertNotNull($media);
        $path = self::getContainer()->getParameter('media_upload_dir').'/'.basename($media->getPath());
        $this->filePaths[] = $path;

        self::assertResponseRedirects('/admin/media');
        self::assertSame($this->guest->getId(), $media->getUser()?->getId());
        self::assertNull($media->getAlbum());
        self::assertFileExists($path);
        self::assertSame(
            hash_file('sha256', self::$kernel->getProjectDir().'/tests/Resources/test-upload-media.jpg'),
            hash_file('sha256', $path)
        );
    }

    /** Protects guest uploads from forged owner or album fields. */
    #[DataProvider('forgedFields')]
    public function testGuestCannotChooseAnOwnerOrAlbum(string $field): void
    {
        $otherGuest = $this->createUser('Other guest');
        $album = (new Album())->setName('Protected album');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($album);
        $manager->flush();
        $mediaCount = $manager->getRepository(Media::class)->count([]);

        $this->client->loginUser($this->guest);
        $crawler = $this->client->request('GET', '/admin/media/add');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name=media]')->form(['media[title]' => 'Forged upload']);
        $values = $form->getPhpValues();
        $values['media'][$field] = 'user' === $field ? $otherGuest->getId() : $album->getId();

        $this->client->request($form->getMethod(), $form->getUri(), $values, [
            'media' => ['file' => $this->uploadedImage()],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('form[name=media]', 'This form should not contain extra fields.');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($mediaCount, $manager->getRepository(Media::class)->count([]));
    }

    /** @return iterable<string, array{string}> */
    public static function forgedFields(): iterable
    {
        yield 'another owner' => ['user'];
        yield 'an album' => ['album'];
    }

    /** Protects guest media listings and pagination from exposing other users' files or admin links. */
    public function testGuestListAndPaginationContainOnlyTheirOwnMedia(): void
    {
        $otherGuest = $this->createUser('Other guest');
        for ($number = 1; $number <= 26; ++$number) {
            $this->createMedia($this->guest, 'My image '.$number);
            $this->createMedia($otherGuest, 'Someone else image '.$number);
        }
        $this->createMedia(null, 'Unowned portfolio image');
        $this->client->loginUser($this->guest);

        $this->client->request('GET', '/admin/media');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'My image 1');
        self::assertSelectorTextContains('tbody', 'My image 25');
        self::assertSelectorTextNotContains('tbody', 'Someone else image');
        self::assertSelectorTextNotContains('tbody', 'Unowned portfolio image');
        self::assertSelectorExists('a[href="/admin/media?page=2"]');
        self::assertSelectorNotExists('a[href="/admin/media?page=3"]');
        self::assertSelectorNotExists('a[href="/admin/album"]');
        self::assertSelectorNotExists('a[href="/admin/guest"]');

        $this->client->request('GET', '/admin/media?page=2');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'My image 26');
        self::assertSelectorNotExists('a[href="/admin/media?page=3"]');
    }

    /** Protects empty, partial, and full single-page galleries from showing needless pagination. */
    #[DataProvider('singlePageMediaCounts')]
    public function testGuestGalleryHidesPaginationForAtMostOnePage(int $mediaCount): void
    {
        for ($number = 1; $number <= $mediaCount; ++$number) {
            $this->createMedia($this->guest, 'My image '.$number);
        }
        $this->client->loginUser($this->guest);

        $this->client->request('GET', '/admin/media');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount($mediaCount, 'tbody tr');
        self::assertSelectorNotExists('.pagination');

        $this->client->request('GET', '/admin/media?page='.PHP_INT_MAX);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount($mediaCount, 'tbody tr');
        self::assertSelectorNotExists('.pagination');
    }

    /** @return iterable<string, array{int}> */
    public static function singlePageMediaCounts(): iterable
    {
        yield 'empty gallery' => [0];
        yield 'one image' => [1];
        yield 'full first page' => [25];
    }

    #[DataProvider('galleryRoles')]
    public function testOutOfRangePageShowsLastPage(bool $admin): void
    {
        for ($number = 1; $number <= 26; ++$number) {
            $this->createMedia($this->guest, 'Pagination boundary image '.$number);
        }
        $user = $admin ? $this->createUser('Pagination boundary admin', true) : $this->guest;
        $this->client->loginUser($user);
        $criteria = $admin ? [] : ['user' => $this->guest];
        $total = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Media::class)->count($criteria);
        $lastPage = (int) ceil($total / 25);

        $crawler = $this->client->request('GET', '/admin/media?page='.$lastPage);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount($total - 25 * ($lastPage - 1), 'tbody tr');
        self::assertSelectorTextContains('.page-item.active', (string) $lastPage);
        $rows = $crawler->filter('tbody')->text();
        $links = $crawler->filter('.pagination a')->extract(['href']);
        self::assertLessThanOrEqual(9, count($links));

        foreach ([$lastPage + 1, 999999, PHP_INT_MAX] as $page) {
            $crawler = $this->client->request('GET', '/admin/media?page='.$page);

            self::assertResponseIsSuccessful();
            self::assertSame($rows, $crawler->filter('tbody')->text());
            self::assertSame($links, $crawler->filter('.pagination a')->extract(['href']));
            self::assertSelectorTextContains('.page-item.active', (string) $lastPage);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function galleryRoles(): iterable
    {
        yield 'guest' => [false];
        yield 'administrator' => [true];
    }

    /** Protects page zero and negative page numbers from producing an empty or broken gallery. */
    #[DataProvider('nonpositivePages')]
    public function testGuestNonpositivePageShowsFirstPage(int $page): void
    {
        $this->createMedia($this->guest, 'My image');
        $this->client->loginUser($this->guest);

        $this->client->request('GET', '/admin/media?page='.$page);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'My image');
    }

    /** @return iterable<string, array{int}> */
    public static function nonpositivePages(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    /** Protects authorized media deletion from leaving orphaned upload files. */
    public function testGuestCanDeleteTheirOwnMediaAndFile(): void
    {
        $media = $this->createMedia($this->guest, 'My image');
        $mediaId = $media->getId();
        $path = self::getContainer()->getParameter('media_upload_dir').'/'.basename($media->getPath());
        $cache = self::getContainer()->get(CacheManager::class);
        $cachePath = $media->getPath();
        $this->cachePaths[] = $cachePath;
        $cache->store(new Binary('cached image', 'image/webp', 'webp'), $cachePath, 'compressed');
        self::assertTrue($cache->isStored($cachePath, 'compressed'));
        $this->client->loginUser($this->guest);
        $crawler = $this->client->request('GET', '/admin/media');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[action="/admin/media/delete/'.$mediaId.'"]')->form());

        self::assertResponseRedirects('/admin/media');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($manager->find(Media::class, $mediaId));
        self::assertFileDoesNotExist($path);
        self::assertFalse(self::getContainer()->get(CacheManager::class)->isStored($cachePath, 'compressed'));
    }

    /** Protects other guests' and unowned media records and files from guest deletion. */
    #[DataProvider('protectedOwners')]
    public function testGuestCannotDeleteMediaTheyDoNotOwn(bool $hasOwner): void
    {
        $owner = $hasOwner ? $this->createUser('Other guest') : null;
        $protectedMedia = $this->createMedia($owner, 'Protected image');
        $protectedId = $protectedMedia->getId();
        $path = self::getContainer()->getParameter('media_upload_dir').'/'.basename($protectedMedia->getPath());
        $fileHash = hash_file('sha256', $path);
        $ownMedia = $this->createMedia($this->guest, 'My image');
        $this->client->loginUser($this->guest);
        $crawler = $this->client->request('GET', '/admin/media');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="/admin/media/delete/'.$ownMedia->getId().'"]')->form();

        $this->client->request('POST', '/admin/media/delete/'.$protectedId, $form->getPhpValues());

        self::assertResponseStatusCodeSame(403);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $media = $manager->find(Media::class, $protectedId);
        self::assertNotNull($media);
        self::assertSame($owner?->getId(), $media->getUser()?->getId());
        self::assertSame('Protected image', $media->getTitle());
        self::assertFileExists($path);
        self::assertSame($fileHash, hash_file('sha256', $path));
    }

    /** @return iterable<string, array{bool}> */
    public static function protectedOwners(): iterable
    {
        yield 'another guest media' => [true];
        yield 'unowned portfolio media' => [false];
    }

    /** Protects album administration and stored albums from guest access or mutation. */
    public function testGuestCannotManageAlbums(): void
    {
        $album = (new Album())->setName('Protected album');
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($album);
        $manager->flush();
        $albumId = $album->getId();
        $this->client->loginUser($this->guest);

        $requests = [
            ['GET', '/admin/album', 403],
            ['GET', '/admin/album/add', 403],
            ['POST', '/admin/album/add', 403],
            ['GET', '/admin/album/update/'.$albumId, 403],
            ['POST', '/admin/album/update/'.$albumId, 403],
            ['GET', '/admin/album/delete/'.$albumId, 405],
            ['POST', '/admin/album/delete/'.$albumId, 403],
        ];
        foreach ($requests as [$method, $url, $status]) {
            $this->client->request($method, $url);
            self::assertResponseStatusCodeSame($status, $method.' '.$url);
        }

        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('Protected album', $manager->find(Album::class, $albumId)?->getName());
    }

    /** Protects admins from losing access to guest-owned and unowned media management. */
    public function testAdminCanStillListAndDeleteGuestAndUnownedMedia(): void
    {
        $admin = $this->createUser('Media test admin', true);
        $guestMedia = $this->createMedia($this->guest, 'Guest image for admin');
        $unownedMedia = $this->createMedia(null, 'Portfolio image for admin');
        $mediaCount = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Media::class)->count([]);
        $this->client->loginUser($admin);

        foreach ([$guestMedia, $unownedMedia] as $media) {
            $mediaId = $media->getId();
            $path = self::getContainer()->getParameter('media_upload_dir').'/'.basename($media->getPath());
            $crawler = $this->client->request('GET', '/admin/media');
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(min(25, $mediaCount), 'tbody tr');
            self::assertSelectorTextContains('thead', 'Artiste');
            self::assertSelectorExists('a[href="/admin/album"]');
            self::assertSelectorExists('a[href="/admin/guest"]');
            $form = $crawler->filter('tbody form')->first()->form();

            $this->client->request('POST', '/admin/media/delete/'.$mediaId, $form->getPhpValues());

            self::assertResponseRedirects('/admin/media');
            $manager = self::getContainer()->get(EntityManagerInterface::class);
            self::assertNull($manager->find(Media::class, $mediaId));
            self::assertFileDoesNotExist($path);
            --$mediaCount;
        }
    }

    /** Protects enabled guest accounts from losing login or media-upload access. */
    public function testEnabledGuestCanLogInAndAccessTheirMedia(): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->guest->setPassword($hasher->hashPassword($this->guest, 'test-password'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Connexion', [
            '_username' => $this->guest->getEmail(),
            '_password' => 'test-password',
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorExists('button:contains("Déconnexion")');

        $this->client->request('GET', '/admin/media/add');
        self::assertResponseIsSuccessful();
    }

    /** Protects disabled accounts from authenticating or accessing media uploads. */
    public function testDisabledGuestCannotLogInOrUpload(): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->guest->setPassword($hasher->hashPassword($this->guest, 'test-password'));
        $this->guest->setDisabledAt(new \DateTimeImmutable());
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Connexion', [
            '_username' => $this->guest->getEmail(),
            '_password' => 'test-password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Votre compte est désactivé');

        $this->client->request('GET', '/admin/media/add');
        self::assertResponseRedirects('http://localhost/login');
    }

    private function createUser(string $name, bool $admin = false): User
    {
        $user = (new User())
            ->setName($name)
            ->setEmail('media-test-'.bin2hex(random_bytes(8)).'@example.com')
            ->setPassword('unused-test-password')
            ->setAdmin($admin);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($user);
        $manager->flush();

        return $user;
    }

    private function createMedia(?User $owner, string $title): Media
    {
        $path = tempnam(self::getContainer()->getParameter('media_upload_dir'), 'guest-media-');
        self::assertNotFalse($path);
        $this->filePaths[] = $path;
        $media = (new Media())
            ->setUser($owner)
            ->setTitle($title)
            ->setPath('uploads/'.basename($path));
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($media);
        $manager->flush();

        return $media;
    }

    private function uploadedImage(): UploadedFile
    {
        return new UploadedFile(
            self::$kernel->getProjectDir().'/tests/Resources/test-upload-media.jpg',
            'photo.jpg',
            'image/jpeg',
            null,
            true
        );
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
