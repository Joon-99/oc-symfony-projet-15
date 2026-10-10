<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\User;
use App\Exception\MediaDeletedFileNotRemovedException;
use App\Form\MediaType;
use App\Service\MediaService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MediaController extends AbstractController
{
    private ManagerRegistry $doctrine;
    private MediaService $mediaService;

    public function __construct(ManagerRegistry $doctrine, MediaService $mediaService)
    {
        $this->doctrine = $doctrine;
        $this->mediaService = $mediaService;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/admin/media', name: 'media_index')]
    public function index(Request $request, #[CurrentUser] ?User $user): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $criteria = [];

        if (!$this->isGranted('ROLE_ADMIN')) {
            $criteria['user'] = $user;
        }

        // Limit the requested page to the last available page before calculating the offset.
        $total = $this->doctrine->getRepository(Media::class)->count($criteria);
        $page = min($page, max(1, (int) ceil($total / 25)));

        $medias = $this->doctrine->getRepository(Media::class)->findBy(
            $criteria,
            ['id' => 'ASC'],
            25,
            25 * ($page - 1)
        );

        return $this->render('admin/media/index.html.twig', [
            'medias' => $medias,
            'total' => $total,
            'page' => $page,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/admin/media/add', name: 'media_add')]
    public function add(Request $request, #[CurrentUser] ?User $user, #[Autowire('%media_upload_dir%')] string $mediaUploadsDir): Response
    {
        $media = new Media();
        $form = $this->createForm(MediaType::class, $media, ['is_admin' => $this->isGranted('ROLE_ADMIN')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $media->setUser($user);
            }
            $fileName = md5(uniqid()).'.'.$media->getFile()->guessExtension();
            $media->setPath('uploads/'.$fileName);
            $media->getFile()->move($mediaUploadsDir, $fileName);
            $this->doctrine->getManager()->persist($media);
            $this->doctrine->getManager()->flush();

            return $this->redirectToRoute('media_index');
        }

        return $this->render('admin/media/add.html.twig', ['form' => $form->createView()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/admin/media/delete/{media}', name: 'media_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('delete_media', '_token')]
    public function delete(Media $media, #[CurrentUser] User $currentUser): Response
    {
        if (!$currentUser->isAdmin() && $media->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException("Vous n'avez pas la permission de supprimer ce média.");
        }
        try {
            $this->mediaService->deleteMedia($media);
        } catch (MediaDeletedFileNotRemovedException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirectToRoute('media_index');
    }
}
