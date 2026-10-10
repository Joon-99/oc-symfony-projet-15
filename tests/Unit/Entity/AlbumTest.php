<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Album;
use PHPUnit\Framework\TestCase;

class AlbumTest extends TestCase
{
    /** Protects against album identifiers or names returning incorrect values. */
    public function testAccessors(): void
    {
        $album = new Album();

        $this->assertNull($album->getId());

        $album->setName('Test Album');
        $this->assertSame('Test Album', $album->getName());
    }
}
