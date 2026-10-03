<?php

namespace App\Tests\Unit;

use App\Entity\Album;
use PHPUnit\Framework\TestCase;

class AlbumTest extends TestCase
{
    public function testAccessors(): void
    {
        $albumData = [
            'name' => 'Test Album',
        ];
        $album = new Album();

        $this->assertNull($album->getId());

        $album->setName($albumData['name']);
        $this->assertSame($albumData['name'], $album->getName());
    }
}
