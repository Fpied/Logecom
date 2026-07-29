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

final class PanierController extends AbstractController
{
    #[Route('/panier', name: 'app_panier', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('panier/index.html.twig', [
            'controller_name' => 'PanierController',
        ]);
    }

    #[Route(
        '/panier/ajouter/{id}',
        name: 'app_panier_ajouter',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_USER')]
    public function ajouter(
        Produit $produit,
        Request $request,
        AjouterRepository $ajouterRepository,
        EntityManagerInterface $entityManager
    ): Response {

        /*
         * 1. Vérification du formulaire.
         *
         * Si le jeton est faux, la méthode s'arrête ici.
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
         * 2. Récupération du client connecté.
         */
        $utilisateur = $this->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException(
                'Vous devez être connecté.'
            );
        }

        /*
         * 3. Vérification de la disponibilité du produit.
         */
        if (!$produit->isActif() || $produit->getStock() <= 0) {
            $this->addFlash(
                'warning',
                'Ce produit n’est pas disponible.'
            );

            return $this->redirectToRoute(
                'app_produit_show',
                ['id' => $produit->getId()]
            );
        }

        /*
         * 4. Récupération du panier du client.
         */
        $panier = $utilisateur->getPanier();

        /*
         * Si le client n'a aucun panier, on le crée.
         */
        if ($panier === null) {
            $panier = new Panier();

            $panier->setUtilisateur($utilisateur);
            $panier->setDateCreate(new \DateTime());
            $panier->setDateMidification(new \DateTime());
            $panier->setStatus('en_cours');

            $entityManager->persist($panier);
        }

        /*
         * 5. Recherche du produit dans le panier.
         */
        $lignePanier = $ajouterRepository->findOneBy([
            'panier' => $panier,
            'produit' => $produit,
        ]);

        /*
         * Si la ligne n'existe pas, la quantité actuelle vaut zéro.
         */
        $quantiteActuelle = $lignePanier?->getQuantite() ?? 0;

        /*
         * 6. Empêcher de dépasser le stock.
         */
        if ($quantiteActuelle >= $produit->getStock()) {
            $this->addFlash(
                'warning',
                'La quantité maximale disponible est déjà dans votre panier.'
            );

            return $this->redirectToRoute(
                'app_produit_show',
                ['id' => $produit->getId()]
            );
        }

        /*
         * 7. Création ou modification de la ligne du panier.
         */
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

        /*
         * 8. Mise à jour de la date du panier.
         */
        $panier->setDateMidification(new \DateTime());

        /*
         * 9. Enregistrement en base.
         */
        $entityManager->flush();

        /*
         * 10. Message affiché au client.
         */
        $this->addFlash(
            'success',
            'Le produit a été ajouté au panier.'
        );

        
        
        $referer = $request->headers->get('referer');

        if ($referer !== null) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
        // return $this->redirectToRoute(
        //     'app_produit_show',
        //     ['id' => $produit->getId()]
        // );
    }
}