<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\ProduitRepository;
use App\Repository\CommandeRepository;
use App\Repository\ContientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_USER')]
final class ClientController extends AbstractController
{
    #[Route('/client', name: 'app_client')]
    public function index(ProduitRepository $produitRepository, CommandeRepository $commandeRepository, ContientRepository $contientRepository): Response
    {
        $utilisateur = $this->getUser();

        $produits = $produitRepository->findBy([
            'utilisateur' => $utilisateur
        ]);

        $commandes = $commandeRepository->findBy([
            'utilisateur' => $utilisateur
        ]);

        $totalAchatsCentimes = 0;

        foreach ($commandes as $commande){
            $totalAchatsCentimes += $commande->getMontantTotalCentimes();
        }

        $lignesCommandes = $contientRepository->findAll();

        $totalVentesCentimes = 0;

        foreach($lignesCommandes as $ligne){
            $produit = $ligne->getProduit();

            if ($produit !== null && $produit->getUtilisateur() === $utilisateur){
                $totalVentesCentimes +=
                $ligne->getQuantite() * $ligne->getPrixUnitaireCentime();
            } 
        }
        
        return $this->render('client/index.html.twig', [
            'controller_name' => 'ClientController',
            'utilisateur' => $utilisateur,
            'produits' => $produits,
            'totalAchatsCentimes' => $totalAchatsCentimes,
            'totalVentesCentimes' => $totalVentesCentimes,
        ]);
    }
}
