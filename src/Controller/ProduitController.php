<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Entity\Image;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/produit')]
final class ProduitController extends AbstractController
{
    #[Route(name: 'app_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        return $this->render('produit/index.html.twig', [
            'produits' => $produitRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_produit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $produit->setUtilisateur($this->getUser());
            $produit->setActif(true);

            /** @var UploadedFile[] $imageFiles */
            $imageFiles = $form->get('imageFiles')->getData();

            if ($imageFiles) {
                foreach ($imageFiles as $index =>  $imageFile) {
                    $filename = $this->uploadImage($imageFile, $slugger);

                    // On enregistre uniquement le nom du fichier en base.
                    $image = new Image();
                    $image->setUrl($filename);
                    $image->setProduit($produit);
                    $image->setMainImage($index === 0); // La première image est l'image principale
                    $image->setOrderImage($index); // Ordre d'affichage

                    $entityManager->persist($image);
                }
            }

            $entityManager->persist($produit);
            $entityManager->flush();

            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile[] $imageFiles */
            $imageFiles = $form->get('imageFiles')->getData();
            
            if($imageFiles){
                $positionDepart = $produit->getImages()->count();
                
                foreach ($imageFiles as $index =>  $imageFile) {
                    $filename = $this->uploadImage($imageFile, $slugger);

                    // On enregistre uniquement le nom du fichier en base.
                    $image = new Image();
                    $image->setUrl($filename);
                    $image->setProduit($produit);
                    $image->setMainImage($positionDepart === 0 && $index === 0); // La première image est l'image principale
                    $image->setOrderImage($positionDepart + $index); // Ordre d'affichage

                    $entityManager->persist($image);
                }
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($produit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_produit_index', [], Response::HTTP_SEE_OTHER);
    }

    private function uploadImage(
    UploadedFile $imageFile,
    SluggerInterface $slugger
    ): string {
        $originalFilename = pathinfo(
            $imageFile->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $safeFilename = $slugger
            ->slug($originalFilename)
            ->lower();

        $extension = $imageFile->guessExtension() ?? 'bin';

        $newFilename = $safeFilename
            . '-'
            . uniqid()
            . '.'
            . $extension;

        try {
            $imageFile->move(
                $this->getParameter('images_directory'),
                $newFilename
            );
        } catch (FileException $e) {
            throw new \RuntimeException(
                'Impossible d’enregistrer l’image.',
                0,
                $e
            );
        }

        return $newFilename;
    }
}
