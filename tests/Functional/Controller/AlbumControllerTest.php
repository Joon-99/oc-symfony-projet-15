<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Album;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AlbumControllerTest extends WebTestCase
{
    private KernelBrowser $client;

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

        $this->client->request('GET', '/admin/album/delete/'.$albumId);

        self::assertResponseRedirects('/admin/album');
        $manager->clear();
        self::assertNull($manager->find(Album::class, $albumId));
    }
}
