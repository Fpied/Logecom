<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_USER')]
final class ClientController extends AbstractController
{
    #[Route('/client', name: 'app_client')]
    public function index(ProduitRepository $produitRepository): Response
    {
        $utilisateur = $this->getUser();

        $produits = $produitRepository->findBy([
            'utilisateur' => $utilisateur
        ]);
        
        return $this->render('client/index.html.twig', [
            'controller_name' => 'ClientController',
            'utilisateur' => $utilisateur,
            'produits' => $produits,
        ]);
    }
}
