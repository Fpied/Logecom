<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Adresse;
use App\Repository\ProduitRepository;
use App\Entity\Commande;
use App\Entity\Utilisateur;
use App\Entity\Paiement;
use Stripe\Checkout\Session;
use App\Entity\Contient;

class CommandeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProduitRepository $produitRepository,
        private StripeCheckoutService $stripeCheckoutService,
        private PanierService $panierService,
    ){


    }

    public function creerCommande(?Utilisateur $utilisateur, Adresse $adresse, array $lignesPanier, Session $checkoutSession): Commande
    {
        $commande = new Commande();
        $commande->setDateCommande(new \DateTime());
        $commande->setUtilisateur($utilisateur);
        $commande->setStatus('payee');
        $commande->setAdresse($adresse);

        $total = $this->panierService->calculerTotal($lignesPanier);
        $commande->setMontantTotalCentimes($total);

        if($checkoutSession->amount_total !== $total) {
            throw new \RuntimeException('Le montant total de la commande ne correspond pas au montant total du panier.');
        }

        $paiement = new Paiement();
        $paiement->setAmountincents($total);
        $paiement->setPaymentDate(new \DateTime());
        $paiement->setPaymentMethod('stripe');
        $paiement->setStatut('paid');
        $paiement->setReferenceTransaction($checkoutSession->id);
        $paiement->setCommande($commande);

        $this->entityManager->persist($commande);
        $this->entityManager->persist($paiement);

        $this->entityManager->beginTransaction();

        try {
            foreach ($lignesPanier as $ligne) {
                $produit = $this->produitRepository->findWithLock($ligne['produit']->getId());
                $quantite = $ligne['quantite'];

                // Vérifier la disponibilité du produit
                if ($produit === null || !$produit->isActif() || $quantite > $produit->getStock()) {
                    $this->entityManager->rollback();
                    $paymentIntendId = $checkoutSession->payment_intent;

                    if($paymentIntendId !== null) {
                        $this->stripeCheckoutService->rembourser($paymentIntendId);
                    }

                    throw new \RuntimeException('Stock insuffisant, remboursement effectué.');
                }

                // Décrémenter la quantité en stock du produit
                $produit->setStock($produit->getStock() - $quantite);
                $this->entityManager->persist($produit);

                // Ajouter la ligne de commande
                $contient = new Contient();
                $contient->setCommande($commande);
                $contient->setProduit($produit);
                $contient->setQuantite($quantite);
                $contient->setPrixUnitaireCentime($produit->getCentPrice());
                $this->entityManager->persist($contient);
            }

        $this->entityManager->flush();

        $this->entityManager->commit();
        } catch (\Throwable $e){
            $this->entityManager->rollback();
            throw $e;
        }
        return $commande;




    } 


        
        
}