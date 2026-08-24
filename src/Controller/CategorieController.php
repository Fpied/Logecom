<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/categorie')]
final class CategorieController extends AbstractController
{
    
    #[Route('/new-ajax', name: 'app_categorie_new_ajax', methods: ['POST'])]
    public function newAjax(
        Request $request,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {

        if (!$this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        if(!$this->isCsrfTokenValid('categorie-create', $request->request->getString('_token'))){
            return $this->json([
                'error' => 'Token CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }
        $nom = trim($request->request->getString('nom'));

        if ($nom === ''){
            return $this->json([
                'error' => 'Le nom de la catégorie est obligatoire.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $categorie = $categorieRepository->findOneBy([
            'nom' => $nom,
        ]);

        if (!$categorie){
            $categorie = new Categorie();
            $categorie->setNom($nom);

            $entityManager->persist($categorie);
            $entityManager->flush();
        }

        return $this->json([
            'id' => $categorie->getId(),
            'nom' => $categorie->getNom(),
        ]);
    }

    
}
