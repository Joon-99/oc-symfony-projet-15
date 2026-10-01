<?php

namespace App\DataFixtures;

use App\Entity\Album;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AlbumFixtures extends Fixture
{
    public const ALBUM_COUNT = 5;
    public const ALBUM_REFERENCE_PREFIX = 'album_';

    public function load(ObjectManager $manager): void
    {
        for ($number = 1; $number <= self::ALBUM_COUNT; $number++) {
            $album = new Album();
            $album->setName("Album {$number}");

            $manager->persist($album);
            $this->addReference(self::ALBUM_REFERENCE_PREFIX . $number, $album);
        }

        $manager->flush();
    }
}
