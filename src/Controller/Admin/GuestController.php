<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\GuestType;
use App\Repository\UserRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GuestController extends AbstractController
{
    private UserRepository $userRepository;
    private UserService $userService;

    public function __construct(UserRepository $userRepository, UserService $userService)
    {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
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

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/guest/disable/{toDisable}', name: 'admin_guest_disable', methods: ['POST'])]
    #[IsCsrfTokenValid('disable_guest', '_token')]
    public function disableUser(User $toDisable): Response {
        if ($toDisable->isAdmin()) {
            throw $this->createAccessDeniedException('Only guests can be disabled.');
        }
        $result = $this->userService->disableUser($toDisable);
        if ($result) {
            $this->addFlash('success', 'Invité désactivé avec succès.');
        } else {
            $this->addFlash('error', "L'invité est déjà désactivé.");
        }

        return $this->redirectToRoute('admin_guest');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/guest/enable/{toEnable}', name: 'admin_guest_enable', methods: ['POST'])]
    #[IsCsrfTokenValid('enable_guest', '_token')]
    public function enableUser(User $toEnable): Response {
        if ($toEnable->isAdmin()) {
            throw $this->createAccessDeniedException('Only guests can be enabled.');
        }
        $result = $this->userService->enableUser($toEnable);
        if ($result) {
            $this->addFlash('success', 'Invité activé avec succès.');
        } else {
            $this->addFlash('error', "L'invité est déjà activé.");
        }

        return $this->redirectToRoute('admin_guest');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/guest/add', name: 'admin_guest_add', methods: ['GET', 'POST'])]
    public function createGuest(Request $request): Response {
        $form = $this->createForm(GuestType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->createGuestUser($form->getData());
            $this->addFlash('success', 'Invité créé avec succès.');
            return $this->redirectToRoute('admin_guest');
        }
        return $this->render('admin/guest/add.html.twig', [
            'form' => $form,
        ]);
    }
}