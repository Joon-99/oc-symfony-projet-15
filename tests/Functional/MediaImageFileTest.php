<?php

namespace App\Tests\Functional;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MediaImageFileTest extends WebTestCase
{
    private string $path;
    private string $originalPath;

    protected function setUp(): void
    {
        parent::setUp();
        static::createClient();

        $this->path = 'uploads/media-test-'.bin2hex(random_bytes(8)).'.jpg';
        $projectDir = self::$kernel->getProjectDir();
        $this->originalPath = $projectDir.'/public/'.$this->path;
        self::assertTrue(copy($projectDir.'/tests/Resources/test-upload-media.jpg', $this->originalPath));
    }

    /** Protects against serving originals instead of WebP or mutating the source image. */
    public function testMediaIsServedAsWebpWithoutChangingOriginal(): void
    {
        $originalHash = hash_file('sha256', $this->originalPath);
        self::assertNotFalse($originalHash);

        static::getClient()->request('GET', '/media/cache/resolve/compressed/'.$this->path);
        self::assertResponseRedirects();

        $cache = self::getContainer()->get(CacheManager::class);
        self::assertTrue($cache->isStored($this->path, 'compressed'));

        $cachedUrlPath = parse_url($cache->resolve($this->path, 'compressed'), PHP_URL_PATH);
        self::assertIsString($cachedUrlPath);
        $cachedPath = self::$kernel->getProjectDir().'/public'.$cachedUrlPath;
        self::assertFileExists($cachedPath);

        $originalSize = getimagesize($this->originalPath);
        $cachedSize = getimagesize($cachedPath);
        self::assertNotFalse($originalSize);
        self::assertNotFalse($cachedSize);
        self::assertSame($originalSize[0], $cachedSize[0]);
        self::assertSame($originalSize[1], $cachedSize[1]);
        self::assertSame('image/webp', $cachedSize['mime']);
        self::assertSame($originalHash, hash_file('sha256', $this->originalPath));
    }

    protected function tearDown(): void
    {
        $cache = self::getContainer()->get(CacheManager::class);
        parent::tearDown();

        try {
            $cache->remove([$this->path], ['compressed']);
        } finally {
            if (is_file($this->originalPath)) {
                unlink($this->originalPath);
            }
        }
    }
}
