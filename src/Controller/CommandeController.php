<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Entity\Adresse;
use App\Entity\Utilisateur;
use App\Form\AdresseType;
use App\Repository\PaiementRepository;
use App\Repository\AdresseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\PanierService;
use App\Service\StripeCheckoutService;
use App\Service\CommandeService;

#[Route('/commande')]
final class CommandeController extends AbstractController
{
    #[Route(name: 'app_commande_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(CommandeRepository $commandeRepository): Response
    {
        $utilisateur = $this->getUser();

        $commandes = $commandeRepository->findCommandesVisiblesByUtilisateur(
            $utilisateur,
        );

        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
            ]);       
    }

    #[Route('/validation', name: 'app_commande_validation', methods: ['GET'])]
    public function validation(Request $request, PanierService $panierService): Response {
            $utilisateur = $this->getUser();
            $lignesPanier = $panierService->getLignesPanier($utilisateur instanceof Utilisateur ? $utilisateur : null, $request);
            if ($lignesPanier === []) {
            $this->addFlash(
                'warning',
                'Votre panier est vide.'
            );
            return $this->redirectToRoute('app_panier');
        }

        $totalPanier = $panierService->calculerTotal($lignesPanier);
        return $this->render(
            'commande/validation.html.twig',
            [
                'lignesPanier' => $lignesPanier,
                'totalPanier' => $totalPanier,
            ]
        );
    }

    #[Route('/adresse', name: 'app_commande_adresse', methods: ['GET', 'POST'])]
    public function adresse(Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $adresse = new Adresse();
        $utilisateur = $this->getUser();
        if ($utilisateur instanceof Utilisateur) {
            $adresse->setUtilisateur($utilisateur);

            $adresse->setFirstname($utilisateur->getFirstname());
            $adresse->setLastname($utilisateur->getNom());
            $adresseExistante = $utilisateur->getAdresses()->last();

            if ($adresseExistante !== false){
                $adresse->setStreet($adresseExistante->getStreet());
                $adresse->setLand($adresseExistante->getLand());
                $adresse->setCp($adresseExistante->getCp());
                $adresse->setCity($adresseExistante->getCity());
                $adresse->setCountry($adresseExistante->getCountry());
            }
        }

        $form = $this->createForm(AdresseType::class, $adresse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($adresse);
            $entityManager->flush();

            $request->getSession()->set(
                'commande_adresse_id',
                $adresse->getId()
            );

            return $this->redirectToRoute('app_commande_paiement');
        }

        return $this->render('adresse/new.html.twig', [
            'adresse' => $adresse,
            'form' => $form,
        ]);
    }

    #[Route('/paiement', name: 'app_commande_paiement', methods: ['GET', 'POST'])]
    public function paiement(
        Request $request,
        PanierService $panierService,
        StripeCheckoutService $stripeCheckoutService,
    ): Response {
        $session = $request->getSession();
        $utilisateur = $this->getUser();

        $lignesPanier = $panierService->getLignesPanier(
            $utilisateur instanceof Utilisateur ? $utilisateur : null,
            $request
        );

        if(!$panierService->validerStock($lignesPanier)){
            return $this->redirectToRoute('app_panier');
        }

        $lineItems = [];
        foreach($lignesPanier as $ligne){
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $ligne['produit']->getTitle(),
                    ],
                    'unit_amount' => $ligne['produit']->getCentPrice(),
                ],
                'quantity' => $ligne['quantite'],
            ];
        }

        $adresseId = $session->get('commande_adresse_id');

        if ($lineItems === []){
            $this->addFlash('warning', 'votre panier est vide.');

            return $this->redirectToRoute('app_panier');
        }

        if ($adresseId === null) {
            return $this->redirectToRoute('app_commande_adresse');
        }

        if ($request->isMethod('POST')) {
            if (
                !$this->isCsrfTokenValid(
                    'choose_payment_method',
                    $request->request->get('_token')
                )
            ) {
                throw $this->createAccessDeniedException(
                    'Jeton CSRF invalide.'
                );
            }

            $paymentMethod = $request->request->get('payment_method');

            if ($paymentMethod !== 'stripe') {
                $this->addFlash(
                    'warning',
                    'Seul le paiement par carte est disponible pour le moment.'
                );

                return $this->redirectToRoute('app_commande_paiement');
            }

            $successUrl = $this->generateUrl(
                'app_commande_paiement_success',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ) . '?session_id={CHECKOUT_SESSION_ID}';

            $cancelUrl = $this->generateUrl(
                'app_commande_paiement',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            try {
                $checkoutSession = $stripeCheckoutService->createCheckoutSession(
                    $lineItems,
                    $successUrl,
                    $cancelUrl
                );
            } catch (ApiErrorException $e) {
                $this->addFlash(
                    'error',
                    'Le service de paiement est temporairement indisponible.'
                );
                return $this->redirectToRoute('app_commande_paiement');
            }
            $session->set('stripe_checkout_session_id', $checkoutSession->id);

            return $this->redirect($checkoutSession->url);
        }
        return $this->render('paiement/new.html.twig', [
            'selected_payment_method' => 'stripe',
        ]);
    }

    #[Route(
    '/paiement/success', name: 'app_commande_paiement_success', methods: ['GET'])]
    public function paiementSuccess(Request $request, EntityManagerInterface $entityManager, 
    PaiementRepository $paiementRepository, AdresseRepository $adresseRepository, PanierService 
    $panierService, StripeCheckoutService $stripeCheckoutService, CommandeService $commandeService)
    : Response {
        $stripeSessionId = $request->query->get('session_id');

        $expectedStripeSessionId = $request
            ->getSession()
            ->get('stripe_checkout_session_id');

        if($expectedStripeSessionId === null || $stripeSessionId !== $expectedStripeSessionId){
            throw $this->createAccessDeniedException('Session de paiement Stripe invalide.');
        }

        if (!$stripeSessionId) {
            return $this->redirectToRoute('app_commande_paiement');
        }

        try {
            $checkoutSession = $stripeCheckoutService->recupererSession($stripeSessionId);
        } catch (ApiErrorException $e) {
            $this->addFlash(
                'error',
                'Le service de paiement est temporairement indisponible.'
            );

            return $this->redirectToRoute('app_commande_paiement');
        }
        if ($checkoutSession->payment_status !== 'paid') {
            $this->addFlash(
                'warning',
                'Le paiement n’a pas été validé.'
            );

            return $this->redirectToRoute('app_commande_paiement');
        }

        $paiementExistant = $paiementRepository->findOneBy([
            'reference_transaction' => $checkoutSession->id,
        ]);

        if ($paiementExistant !== null) {
            return $this->render('commande/confirmation.html.twig', [
                'stripe_session_id' => $checkoutSession->id,
            ]);
        }

            $utilisateur = $this->getUser();
            $lignesPanier = $panierService->getLignesPanier(
                $utilisateur instanceof Utilisateur ? $utilisateur : null,
                $request
            );

            if ($lignesPanier === []) {
                return $this->redirectToRoute('app_panier');
            }

            $adresseId = $request->getSession()->get('commande_adresse_id');
            $adresse = $adresseRepository->find($adresseId);

            if ($adresse === null) {
                return $this->redirectToRoute('app_commande_adresse');
            }

            if ($utilisateur instanceof Utilisateur && $adresse->getUtilisateur() !== $utilisateur) {
                throw $this->createAccessDeniedException('Adresse invalide.');
            }

            try {
                $commande = $commandeService->creerCommande(
                    $utilisateur instanceof Utilisateur ? $utilisateur : null,
                    $adresse,
                    $lignesPanier,
                    $checkoutSession
                );
            } catch (\RuntimeException $e) {
                return $this->redirectToRoute('app_panier');
            }

            if ($utilisateur instanceof Utilisateur) {
                $panier = $utilisateur->getPanier();

                foreach ($panier->getAjouters() as $ligne) {
                    $entityManager->remove($ligne);
                }

                $entityManager->flush();
            }
            if (!$utilisateur instanceof Utilisateur) {
                $request->getSession()->remove('panier');
            }

            $request->getSession()->remove('commande_adresse_id');
            $request->getSession()->remove('stripe_checkout_session_id');

        return $this->render('commande/confirmation.html.twig', [
            'stripe_session_id' => $checkoutSession->id,
        ]);
    }
    
    #[Route('/{id}', name: 'app_commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        $utilisateur = $this->getUser();

        if(!$utilisateur instanceof Utilisateur || $commande->getUtilisateur() !== $utilisateur){
            throw $this->createAccessDeniedException('Accès interdit.');
        }
        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/{id}', name: 'app_commande_delete', methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $entityManager, 
    StripeCheckoutService $stripeCheckoutService): Response
    {
        $utilisateur = $this->getUser();

        if(!$utilisateur instanceof Utilisateur || $commande->getUtilisateur() !== $utilisateur){
            throw $this->createAccessDeniedException('Accès interdit.');
        }
        if ($this->isCsrfTokenValid('delete'.$commande->getId(), $request->getPayload()->getString('_token'))) {
            $paiement = $commande->getPaiement();
            if ($paiement !== null && $paiement->getStatut() === 'paid'){
                try {
                    $checkoutSession = $stripeCheckoutService->recupererSession(
                        $paiement->getReferenceTransaction()
                    );

                    $paymentIntentId = $checkoutSession->payment_intent;

                    if ($paymentIntentId === null) {
                        throw $this->createNotFoundException(
                            'Aucun paiement Stripe associé à cette commande.'
                        );
                    }

                    $stripeCheckoutService->rembourser($paymentIntentId);
                } catch (ApiErrorException $e) {
                    $this->addFlash(
                        'error',
                        'Le remboursement n’a pas pu être effectué.'
                    );

                    return $this->redirectToRoute('app_commande_index');
                }

                foreach($commande->getContients() as $ligne){
                    $produit = $ligne->getProduit();

                    if($produit !== null){
                        $produit->setStock(
                            $produit->getStock() + $ligne->getQuantite()
                        );
                    }
                }

                $paiement->setStatut('refunded');
                $commande->setStatus('remboursee');
            } else {
                $commande->setStatus('annulee');
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_commande_index', [], Response::HTTP_SEE_OTHER);
    }
}
