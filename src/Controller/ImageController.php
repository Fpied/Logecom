<?php

namespace App\Controller;

use App\Entity\Image;
use App\Form\ImageType;
use App\Entity\Produit;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/image')]
final class ImageController extends AbstractController
{
    #[Route(name: 'app_image_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ImageRepository $imageRepository): Response
    {
        $utilisateur = $this->getUser();
        $imagesUtilisateur = [];

        foreach ($imageRepository->findAll() as $image){
            $produit = $image->getProduit();

            if ($produit !== null && $produit->getUtilisateur() === $utilisateur){
                $imagesUtilisateur[] = $image;
            }
        }
        return $this->render('image/index.html.twig', [
            'images' => $imagesUtilisateur,
        ]);
    }

    #[Route('/new/{id}', name: 'app_image_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if ($produit->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        $image = new Image();
        $image->setProduit($produit);
        $form = $this->createForm(ImageType::class, $image, ['image_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile !== null) {
                $filename = $this->uploadImage($imageFile, $slugger);

                // On enregistre uniquement le nom du fichieer en base.
                $image->setUrl($filename);
                
            }

            $entityManager->persist($image);
            $entityManager->flush();

            return $this->redirectToRoute('app_image_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('image/new.html.twig', [
            'image' => $image,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_image_show', methods: ['GET'])]
    public function show(Image $image): Response
    {
        if($image->getProduit()->getUtilisateur() !== $this->getUser()){
            throw $this->createAccessDeniedException('Accès interdit.');
        }
        return $this->render('image/show.html.twig', [
            'image' => $image,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_image_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Image $image, EntityManagerInterface $entityManager): Response
    {
        if ($image->getProduit()->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        $form = $this->createForm(ImageType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_image_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('image/edit.html.twig', [
            'image' => $image,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_image_delete', methods: ['POST'])]
    public function delete(Request $request, Image $image, EntityManagerInterface $entityManager): Response
    {
        if ($image->getProduit()->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        if ($this->isCsrfTokenValid('delete'.$image->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($image);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_image_index', [], Response::HTTP_SEE_OTHER);
    }

    private function uploadImage(UploadedFile $imageFile, SluggerInterface $slugger): string
    {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

        try {
            $imageFile->move(
                $this->getParameter('images_directory'),
                $newFilename
            );
        } catch (FileException $e) {
            // Gérer l'erreur de téléchargement du fichier si nécessaire
            throw new \RuntimeException('Erreur lors du téléchargement de l\'image : '.$e->getMessage());
        }

        return $newFilename;
    }
}
