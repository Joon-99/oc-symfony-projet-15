<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private UserRepository $userRepository;
    private AlbumRepository $albumRepository;
    private MediaRepository $mediaRepository;
    private UserService $userService;

    public function __construct(
        UserRepository $userRepository,
        AlbumRepository $albumRepository,
        MediaRepository $mediaRepository,
        UserService $userService
    ) {
        $this->userRepository = $userRepository;
        $this->albumRepository = $albumRepository;
        $this->mediaRepository = $mediaRepository;
        $this->userService = $userService;
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('front/home.html.twig');
    }

    #[Route('/guests', name: 'guests')]
    public function guests(): Response
    {
        $guests = $this->userService->getEnabledGuestsAndMediaCount();

        return $this->render('front/guests.html.twig', [
            'guests' => $guests,
        ]);
    }

    #[Route('/guest/{guest}', name: 'guest', requirements: ['guest' => '\d+'])]
    public function guest(User $guest): Response
    {
        if ($guest->isAdmin()) {
            throw $this->createNotFoundException();
        }
        if (!$guest->isActive() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Cet utilisateur est inactif.');
        }
        return $this->render('front/guest.html.twig', [
            'guest' => $guest,
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
            'medias' => $medias,
        ]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }
}
