<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const GUEST_COUNT = 100;
    public const ADMIN_REFERENCE = 'user_admin';
    public const GUEST_REFERENCE_PREFIX = 'user_guest_';

    private const GUEST_DESCRIPTION = "Le maître de l'urbanité capturée, explore les méandres des cités avec un "
        ."regard vif et impétueux, figeant l'énergie des rues dans des instants éblouissants. À travers une "
        ."technique avant-gardiste, il métamorphose le béton et l'acier en toiles abstraites, révélant l'essence "
        ."même de l'architecture moderne. Ses clichés transcendent les formes familières pour révéler des "
        .'perspectives inattendues, offrant une vision nouvelle et captivante du monde urbain.';

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setAdmin(true);
        $admin->setName('Ina Zaoui');
        $admin->setEmail('ina@zaoui.com');
        $admin->setDescription(null);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password'));

        $manager->persist($admin);
        $this->addReference(self::ADMIN_REFERENCE, $admin);

        for ($number = 0; $number < self::GUEST_COUNT; ++$number) {
            $guest = new User();
            $guest->setAdmin(false);
            $guest->setName("Invité {$number}");
            $guest->setEmail("invite+{$number}@example.com");
            $guest->setDescription(self::GUEST_DESCRIPTION);

            $guest->setPassword($this->passwordHasher->hashPassword($guest, 'password'));

            $manager->persist($guest);
            $this->addReference(self::GUEST_REFERENCE_PREFIX.$number, $guest);
        }

        $manager->flush();
    }
}
