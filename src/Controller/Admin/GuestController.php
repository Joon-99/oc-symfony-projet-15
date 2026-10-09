<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GuestController extends AbstractController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/guest', name: 'admin_guest')]
    public function index(): Response
    {
        $guests = $this->userRepository->findByAdmin(false);

        return $this->render('admin/guest/index.html.twig', [
            'guests' => $guests,
        ]);
    }
}