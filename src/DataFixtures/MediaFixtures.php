<?php

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Gives every user 50 photos (matching the real files under public/uploads), and splits
 * only the admin's photos evenly across the 5 albums - guests' photos stay unassigned.
 */
class MediaFixtures extends Fixture implements DependentFixtureInterface
{
    public const MEDIA_PER_USER = 50;
    private const MEDIA_PER_ALBUM = 10;

    public function load(ObjectManager $manager): void
    {
        $uploadNumber = 1;

        foreach ($this->getUsers() as $user) {
            for ($titleNumber = 0; $titleNumber < self::MEDIA_PER_USER; $titleNumber++) {
                $media = new Media();
                $media->setUser($user);
                $media->setTitle("Titre {$titleNumber}");
                $media->setPath(sprintf('uploads/%04d.jpg', $uploadNumber));

                if ($user->isAdmin()) {
                    $media->setAlbum($this->getAlbumForMedia($titleNumber));
                }

                $manager->persist($media);
                $uploadNumber++;
            }
        }

        $manager->flush();
    }

    private function getAlbumForMedia(int $titleNumber): Album
    {
        $albumNumber = intdiv($titleNumber, self::MEDIA_PER_ALBUM) + 1;

        return $this->getReference(AlbumFixtures::ALBUM_REFERENCE_PREFIX . $albumNumber, Album::class);
    }

    /**
     * @return iterable<User>
     */
    private function getUsers(): iterable
    {
        yield $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);

        for ($number = 0; $number < UserFixtures::GUEST_COUNT; $number++) {
            yield $this->getReference(UserFixtures::GUEST_REFERENCE_PREFIX . $number, User::class);
        }
    }

    public function getDependencies(): array
    {
        return [AlbumFixtures::class, UserFixtures::class];
    }
}
