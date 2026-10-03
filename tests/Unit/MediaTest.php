<?php

namespace App\Tests\Unit;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaTest extends TestCase
{
    public function testAccessors(): void
    {
        $mediaData = [
            'user' => new User(),
            'album' => new Album(),
            'path' => 'destination/path/to/media',
            'title' => 'Media Title',
            'file' => $this->createStub(UploadedFile::class),
        ];
        $media = new Media();

        $this->assertNull($media->getId());

        $media->setUser($mediaData['user']);
        $this->assertSame($mediaData['user'], $media->getUser());

        $media->setAlbum($mediaData['album']);
        $this->assertSame($mediaData['album'], $media->getAlbum());

        $media->setPath($mediaData['path']);
        $this->assertSame($mediaData['path'], $media->getPath());

        $media->setTitle($mediaData['title']);
        $this->assertSame($mediaData['title'], $media->getTitle());

        $media->setFile($mediaData['file']);
        $this->assertSame($mediaData['file'], $media->getFile());
    }
}
