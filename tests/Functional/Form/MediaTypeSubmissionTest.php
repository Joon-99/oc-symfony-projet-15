<?php

namespace App\Tests\Functional\Form;

use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaTypeSubmissionTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $projectDir;
    private string $testFilePath;
    private ?string $mediaFilePathToDeletePath = null;
    private UserRepository $userRepository;
    private MediaRepository $mediaRepository;
    private AlbumRepository $albumRepository;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->projectDir = self::$kernel->getProjectDir();
        $this->testFilePath = $this->projectDir.'/tests/Resources/test-upload-media.jpg';

        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->mediaRepository = self::getContainer()->get(MediaRepository::class);
        $this->albumRepository = self::getContainer()->get(AlbumRepository::class);

        $this->loginUser('ina@zaoui.com');
    }

    /** Protects against required media fields disappearing from the upload form. */
    public function testFormLoads(): void
    {
        $this->client->request('GET', '/admin/media/add');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#media_user');
        $this->assertSelectorExists('#media_album');
        $this->assertSelectorExists('#media_title');
        $this->assertSelectorExists('#media_file');
    }

    /** Protects against valid uploads failing to persist media metadata and image files. */
    public function testFormSubmission(): void
    {
        $userTest = $this->userRepository->findOneBy(['email' => 'invite+0@example.com']);
        $albumTest = $this->albumRepository->findOneBy(['name' => 'Album 1']);
        $this->assertNotNull($userTest);
        $this->assertNotNull($albumTest);

        $title = 'Test Media PHPUnit '.uniqid();
        $userId = $userTest->getId();
        $albumId = $albumTest->getId();
        $formData = [
            'media[user]' => $userId,
            'media[album]' => $albumId,
            'media[title]' => $title,
        ];

        $crawler = $this->client->request('GET', '/admin/media/add');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name=media]')->form();
        $fileField = $form['media[file]'];
        $this->assertInstanceOf(FileFormField::class, $fileField);

        $fileField->upload($this->testFilePath); // filling up the file field separately to stay close to browser behavior
        $this->client->submit($form, $formData);

        $media = $this->mediaRepository->findOneBy(['title' => $title]);

        $this->assertNotNull($media);

        // Store the path of the uploaded media file for cleanup in tearDown()
        $uploadsDir = $this->getContainer()->getParameter('media_upload_dir');
        $this->mediaFilePathToDeletePath = Path::join($uploadsDir, basename($media->getPath()));

        $this->assertResponseRedirects('/admin/media');

        $this->assertFileExists($this->mediaFilePathToDeletePath);
        $this->assertSame($title, $media->getTitle());
        $this->assertSame($userId, $media->getUser()?->getId());
        $this->assertSame($albumId, $media->getAlbum()?->getId());
    }

    private function loginUser(string $username): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Connexion', [
            '_username' => $username,
            '_password' => 'password',
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button:contains("Déconnexion")');
    }

    /** Protects against invalid or oversized uploads being accepted or saved. */
    #[DataProvider('invalidUploadProvider')]
    public function testInvalidUploadIsRejected(bool $oversized, string $message): void
    {
        $path = tempnam(sys_get_temp_dir(), 'media-upload-');
        $this->assertNotFalse($path);

        try {
            $contents = 'This is not an image.';
            if ($oversized) {
                $contents = file_get_contents($this->testFilePath);
                $this->assertNotFalse($contents);
                $contents = str_pad($contents, 2_000_001, "\0");
            }
            $this->assertSame(strlen($contents), file_put_contents($path, $contents));

            $title = 'Invalid Media PHPUnit '.uniqid();
            $crawler = $this->client->request('GET', '/admin/media/add');
            $form = $crawler->filter('form[name=media]')->form(['media[title]' => $title]);
            $this->client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), [
                'media' => ['file' => new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true)],
            ]);

            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('form[name=media]', $message);
            $this->assertNull($this->mediaRepository->findOneBy(['title' => $title]));
            $this->assertFileExists($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function invalidUploadProvider(): array
    {
        return [
            'fake JPEG' => [false, "Le type de fichier n'est pas valide"],
            'image above 2 MB' => [true, 'Le fichier est trop volumineux'],
        ];
    }

    #[\Override]
    public function tearDown(): void
    {
        parent::tearDown();
        if ($this->mediaFilePathToDeletePath && file_exists($this->mediaFilePathToDeletePath)) {
            unlink($this->mediaFilePathToDeletePath);
        }
    }
}
