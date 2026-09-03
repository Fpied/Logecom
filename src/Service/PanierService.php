<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\AjouterRepository;
use App\Repository\ProduitRepository;
use Symfony\Component\HttpFoundation\Request;

class PanierService
{
    public function __construct(
        private AjouterRepository $ajouterRepository,
        private ProduitRepository $produitRepository,
    ) {
    }   
    public function getLignesPanier(?Utilisateur $utilisateur, Request $request): array
    {
        $lignesPanier = [];

        if ($utilisateur instanceof Utilisateur) {
            $panier = $utilisateur->getPanier();

            if ($panier !== null) {
                $lignes = $this->ajouterRepository->findBy([
                    'panier' => $panier,
                ]);

                foreach ($lignes as $ligne) {
                    $lignesPanier[] = [
                        'produit' => $ligne->getProduit(),
                        'quantite' => $ligne->getQuantite(),
                    ];
                }
            }
        }

        else {
            // Utilisateur non connecté, récupérer les données du panier depuis la session
            $session = $request->getSession();
            $panierSession = $session->get('panier', []);

            foreach ($panierSession as $produitId => $quantite) {
                $produit = $this->produitRepository->find($produitId);
                if ($produit) {
                    $lignesPanier[] = [
                        'produit' => $produit,
                        'quantite' => $quantite,
                    ];
                }
            }
        }
        return $lignesPanier;
    }
    // Service implementation

    public function calculerTotal(array $lignePanier): int
    {
        $total = 0;

        foreach ($lignePanier as $ligne) {
            $total+= $ligne['produit']->getCentPrice() * $ligne['quantite'];
        }

        return $total;
    }

    public function validerStock(array $lignePanier): bool
    {
        foreach ($lignePanier as $ligne) {
            if ($ligne['produit'] === null || !$ligne['produit']->isActif() || $ligne['quantite'] < 1 || $ligne['quantite'] > $ligne['produit']->getStock()) {
                return false;
            }
        }
        return true;
    }
}