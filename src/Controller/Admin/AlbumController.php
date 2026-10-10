<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Exception\MediaDeletedFileNotRemovedException;
use App\Form\AlbumType;
use App\Service\AlbumService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class AlbumController extends AbstractController
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    #[Route('/admin/album', name: 'admin_album_index')]
    public function index(): Response
    {
        $albums = $this->doctrine->getRepository(Album::class)->findAll();

        return $this->render('admin/album/index.html.twig', ['albums' => $albums]);
    }

    #[Route('/admin/album/add', name: 'admin_album_add')]
    public function add(Request $request): Response
    {
        $album = new Album();
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->doctrine->getManager()->persist($album);
            $this->doctrine->getManager()->flush();

            return $this->redirectToRoute('admin_album_index');
        }

        return $this->render('admin/album/add.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/album/update/{album}', name: 'admin_album_update', requirements: ['album' => '\d+'])]
    public function update(Request $request, Album $album): Response
    {
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->doctrine->getManager()->flush();

            return $this->redirectToRoute('admin_album_index');
        }

        return $this->render('admin/album/update.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/album/delete/{album}', name: 'admin_album_delete', requirements: ['album' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid('delete_album', '_token')]
    public function delete(Album $album, AlbumService $albumService): Response
    {
        try {
            $albumService->deleteAlbum($album);
        } catch (MediaDeletedFileNotRemovedException $e) {
            $this->addFlash('warning', "L'album et ses médias ont été supprimés, mais certains fichiers n'ont pas pu être supprimés.");
        }

        return $this->redirectToRoute('admin_album_index');
    }
}
