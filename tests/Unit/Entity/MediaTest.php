<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MediaTest extends TestCase
{
    private string|false $path;
    private ValidatorInterface $validator;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'media-validation-');
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    /** Protects against media accessors returning or storing the wrong values. */
    public function testAccessors(): void
    {
        $user = new User();
        $album = new Album();
        $path = 'destination/path/to/media';
        $title = 'Media Title';
        $file = $this->createStub(UploadedFile::class);
        $media = new Media();

        $this->assertNull($media->getId());

        $media->setUser($user);
        $this->assertSame($user, $media->getUser());

        $media->setAlbum($album);
        $this->assertSame($album, $media->getAlbum());

        $media->setPath($path);
        $this->assertSame($path, $media->getPath());

        $media->setTitle($title);
        $this->assertSame($title, $media->getTitle());

        $media->setFile($file);
        $this->assertSame($file, $media->getFile());
    }

    /** Protects against image-size limits allowing oversized files or rejecting the boundary. */
    #[DataProvider('imageSizeProvider')]
    public function testImageSizeValidation(int $size, ?string $expectedViolationCode): void
    {
        $this->assertNotFalse($this->path);

        $contents = file_get_contents(Path::join(__DIR__, '../../Resources/test-upload-media.jpg'));
        $this->assertNotFalse($contents);
        $this->assertSame($size, file_put_contents($this->path, str_pad($contents, $size, "\0")));

        $media = (new Media())->setFile(new UploadedFile($this->path, 'photo.jpg', 'image/jpeg', null, true));
        $violations = $this->validator->validateProperty($media, 'file');

        $this->assertContainsViolationCode($expectedViolationCode, $violations);
    }

    /**
     * @return array<string, array{int, ?string}>
     */
    public static function imageSizeProvider(): array
    {
        return [
            'below 2 MB' => [1_999_999, null],
            'exactly 2 MB' => [2_000_000, null],
            'above 2 MB' => [2_000_001, Image::TOO_LARGE_ERROR],
        ];
    }

    /** Protects against disallowed image formats being accepted or valid formats rejected. */
    #[DataProvider('imageTypeProvider')]
    public function testImageTypeValidation(string $mimeType, ?string $expectedViolationCode): void
    {
        match ($mimeType) {
            'image/jpeg' => imagejpeg(imagecreatetruecolor(2, 2), $this->path),
            'image/png' => imagepng(imagecreatetruecolor(2, 2), $this->path),
            'image/gif' => imagegif(imagecreatetruecolor(2, 2), $this->path),
            'image/webp' => imagewebp(imagecreatetruecolor(2, 2), $this->path),
            'image/svg+xml' => file_put_contents($this->path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'application/pdf' => file_put_contents($this->path, 'not an image'),
            default => throw new \LogicException("No fixture content defined for MIME type \"{$mimeType}\"."),
        };

        $media = (new Media())->setFile(new UploadedFile($this->path, 'photo', $mimeType, null, true));
        $violations = $this->validator->validateProperty($media, 'file');

        $this->assertContainsViolationCode($expectedViolationCode, $violations);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function imageTypeProvider(): array
    {
        return [
            'JPEG is accepted' => ['image/jpeg', null],
            'PNG is accepted' => ['image/png', null],
            'GIF is accepted' => ['image/gif', null],
            'WEBP is accepted' => ['image/webp', null],
            // SVG is rejected on purpose: uploads are served as plain files, with no sanitization.
            // From my research, this is not considered framework-level and is usually handled through a dedicated sanitizer
            // Revisit this exclusion only if either:
            // 1. the situation changes with regards to SVG sanitization in default Symfony
            // or
            // 2. you make sure to handle the sanitization @see enshrined/svg-sanitize
            'SVG is rejected' => ['image/svg+xml', Image::INVALID_MIME_TYPE_ERROR],
            'Non-image types are rejected' => ['application/pdf', Image::INVALID_MIME_TYPE_ERROR],
        ];
    }

    /** Protects against forged JPEG headers bypassing corrupt-image validation. */
    public function testForgedImageHeaderIsRejected(): void
    {
        $jpegStartSequence = "\xFF\xD8\xFF";
        file_put_contents($this->path, $jpegStartSequence.str_repeat('not-a-real-jpeg-body', 10));

        $media = (new Media())->setFile(new UploadedFile($this->path, 'photo.jpg', 'image/jpeg', null, true));
        $violations = $this->validator->validateProperty($media, 'file');

        $this->assertContainsViolationCode(Image::SIZE_NOT_DETECTED_ERROR, $violations);
    }

    /** Protects against the optional transient upload field becoming mandatory during validation. */
    public function testFileIsOptionalOnValidation(): void
    {
        $this->assertCount(0, $this->validator->validateProperty(new Media(), 'file'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->path && file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Asserts the expected violation code is present.
     * Codes are translated to readable names via Image::getErrorName() used to make errors readable in phpunit output.
     */
    private function assertContainsViolationCode(?string $expectedCode, ConstraintViolationListInterface $violations): void
    {
        $names = array_map(
            static fn ($violation) => Image::getErrorName($violation->getCode()),
            iterator_to_array($violations)
        );

        if (null === $expectedCode) {
            $this->assertSame([], $names);
        } else {
            $this->assertContains(Image::getErrorName($expectedCode), $names);
        }
    }
}
