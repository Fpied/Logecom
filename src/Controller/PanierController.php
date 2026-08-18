<?php

namespace App\Controller;

use App\Entity\Ajouter;
use App\Entity\Panier;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Repository\AjouterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\ProduitRepository;

final class PanierController extends AbstractController
{
    #[Route('/panier', name: 'app_panier', methods: ['GET'])]
public function index(
    Request $request,
    AjouterRepository $ajouterRepository,
    ProduitRepository $produitRepository
    ): Response {
        $utilisateur = $this->getUser();
        $lignesPanier = [];

        /*
        * Cas 1 : utilisateur connecté
        * Le panier est récupéré depuis la base de données.
        */
        if ($utilisateur instanceof Utilisateur) {
            $panier = $utilisateur->getPanier();

            if ($panier !== null) {
                $lignes = $ajouterRepository->findBy([
                    'panier' => $panier,
                ]);

                foreach ($lignes as $ligne) {
                    $lignesPanier[] = [
                        'produit' => $ligne->getProduit(),
                        'quantite' => $ligne->getQuantite(),
                    ];
                }
            }
        } else {
            /*
            * Cas 2 : visiteur
            * Le panier est récupéré depuis la session.
            */
            $panierSession = $request->getSession()->get('panier', []);

            foreach ($panierSession as $produitId => $quantite) {
                $produit = $produitRepository->find($produitId);

                if ($produit === null) {
                    continue;
                }

                $lignesPanier[] = [
                    'produit' => $produit,
                    'quantite' => $quantite,
                ];
            }
        }

        $totalPanier = 0;

        foreach ($lignesPanier as $ligne) {
            $totalPanier +=
                $ligne['produit']->getCentPrice()
                * $ligne['quantite'];
        }

        return $this->render('panier/index.html.twig', [
            'lignesPanier' => $lignesPanier,
            'totalPanier' => $totalPanier,
        ]);
    }

    #[Route(
        '/panier/ajouter/{id}',
        name: 'app_panier_ajouter',
        methods: ['POST']
    )]
    public function ajouter(
        Produit $produit,
        Request $request,
        AjouterRepository $ajouterRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /*
         * 1. Vérification du jeton CSRF
         */
        if (!$this->isCsrfTokenValid(
            'ajouter-panier-' . $produit->getId(),
            $request->getPayload()->getString('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        /*
         * 2. Vérification de la disponibilité
         */
        if (!$produit->isActif() || $produit->getStock() <= 0) {
            $this->addFlash(
                'warning',
                'Ce produit n’est pas disponible.'
            );

            return $this->redirigerApresAjout($request, $produit);
        }

        /*
         * 3. Récupération de l’utilisateur connecté
         */
        $utilisateur = $this->getUser();

        /*
         * Cas 1 : visiteur non connecté
         */
        if (!$utilisateur instanceof Utilisateur) {
            $session = $request->getSession();

            $panierSession = $session->get('panier', []);

            $produitId = $produit->getId();
            $quantiteActuelle = $panierSession[$produitId] ?? 0;

            if ($quantiteActuelle >= $produit->getStock()) {
                $this->addFlash(
                    'warning',
                    'La quantité maximale disponible est déjà dans votre panier.'
                );

                return $this->redirigerApresAjout($request, $produit);
            }

            $panierSession[$produitId] = $quantiteActuelle + 1;

            $session->set('panier', $panierSession);

            $this->addFlash(
                'success',
                'Le produit a été ajouté au panier.'
            );

            return $this->redirigerApresAjout($request, $produit);
        }

        /*
         * Cas 2 : utilisateur connecté
         */
        $panier = $utilisateur->getPanier();

        if ($panier === null) {
            $panier = new Panier();

            $panier->setUtilisateur($utilisateur);
            $panier->setDateCreate(new \DateTime());
            $panier->setDateMidification(new \DateTime());
            $panier->setStatus('en_cours');

            $entityManager->persist($panier);
        }

        $lignePanier = $ajouterRepository->findOneBy([
            'panier' => $panier,
            'produit' => $produit,
        ]);

        $quantiteActuelle = $lignePanier?->getQuantite() ?? 0;

        if ($quantiteActuelle >= $produit->getStock()) {
            $this->addFlash(
                'warning',
                'La quantité maximale disponible est déjà dans votre panier.'
            );

            return $this->redirigerApresAjout($request, $produit);
        }

        if ($lignePanier === null) {
            $lignePanier = new Ajouter();

            $lignePanier->setPanier($panier);
            $lignePanier->setProduit($produit);
            $lignePanier->setQuantite(1);

            $entityManager->persist($lignePanier);
        } else {
            $lignePanier->setQuantite(
                $lignePanier->getQuantite() + 1
            );
        }

        $panier->setDateMidification(new \DateTime());

        $entityManager->flush();

        $this->addFlash(
            'success',
            'Le produit a été ajouté au panier.'
        );

        return $this->redirigerApresAjout($request, $produit);
    }

    #[Route('/panier/supprimer/{id}', name: 'app_panier_supprimer', methods: ['POST'])]
    public function supprimer( Produit $produit, Request $request, AjouterRepository $ajouterRepository, EntityManagerInterface $entityManager): Response {
        if (!$this->isCsrfTokenValid(
            'supprimer-panier-' . $produit->getId(),
            $request->getPayload()->getString('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton de sécurité invalide.'
            );
        }

        $utilisateur = $this->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            $session = $request->getSession();
            $panierSession = $session->get('panier', []);

            unset($panierSession[$produit->getId()]);

            $session->set('panier', $panierSession);

            $this->addFlash(
                'success',
                'Le produit a été supprimé du panier.'
            );

            return $this->redirectToRoute('app_panier');
        }

        $panier = $utilisateur->getPanier();

        if ($panier === null) {
            return $this->redirectToRoute('app_panier');
        }

        $lignePanier = $ajouterRepository->findOneBy([
            'panier' => $panier,
            'produit' => $produit,
        ]);

        if ($lignePanier !== null) {
            $entityManager->remove($lignePanier);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Le produit a été supprimé du panier.'
            );
        }

        return $this->redirectToRoute('app_panier');
    }

    private function redirigerApresAjout(
        Request $request,
        Produit $produit
    ): Response {
        $referer = $request->headers->get('referer');

        if ($referer !== null) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute(
            'app_produit_show',
            ['id' => $produit->getId()]
        );
    }
}