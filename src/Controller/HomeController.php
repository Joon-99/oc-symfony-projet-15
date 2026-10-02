<?php

namespace App\Controller;

use App\Entity\Album;
use App\Repository\UserRepository;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private UserRepository $userRepository;
    private AlbumRepository $albumRepository;
    private MediaRepository $mediaRepository;

    public function __construct(UserRepository $userRepository, AlbumRepository $albumRepository, MediaRepository $mediaRepository) {
        $this->userRepository = $userRepository;
        $this->albumRepository = $albumRepository;
        $this->mediaRepository = $mediaRepository;
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('front/home.html.twig');
    }

    #[Route('/guests', name: 'guests')]
    public function guests(): Response
    {
        $guests = $this->userRepository->findBy(['admin' => false]);
        return $this->render('front/guests.html.twig', [
            'guests' => $guests
        ]);
    }

    #[Route('/guest/{id}', name: 'guest', requirements: ['id' => '\d+'])]
    public function guest(int $id): Response
    {
        $guest = $this->userRepository->find($id);
        return $this->render('front/guest.html.twig', [
            'guest' => $guest
        ]);
    }

    #[Route('/portfolio/{album}', name: 'portfolio', requirements: ['album' => '\d+'])]
    public function portfolio(
        ?Album $album = null): Response
    {
        $albums = $this->albumRepository->findAll();
        $admin = $this->userRepository->findOneByAdmin(true);

        $medias = $album ? $this->mediaRepository->findByAlbum($album) : $this->mediaRepository->findByUser($admin);
        return $this->render('front/portfolio.html.twig', [
            'albums' => $albums,
            'album' => $album,
            'medias' => $medias
        ]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }
}