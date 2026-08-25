<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Repository\AjouterRepository;
use App\Repository\ProduitRepository;
use App\Entity\Adresse;
use App\Entity\Utilisateur;
use App\Form\AdresseType;
use App\Entity\Contient;
use App\Repository\PaiementRepository;
use App\Entity\Paiement;
use App\Repository\AdresseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


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
    public function validation(Request $request, AjouterRepository $ajouterRepository,
            ProduitRepository $produitRepository): Response {
            $utilisateur = $this->getUser();
            $lignesPanier = [];

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
            $panierSession = $request
                ->getSession()
                ->get('panier', []);

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

        if ($lignesPanier === []) {
            $this->addFlash(
                'warning',
                'Votre panier est vide.'
            );

            return $this->redirectToRoute('app_panier');
        }

        $totalPanier = 0;

        foreach ($lignesPanier as $ligne) {
            $totalPanier +=
                $ligne['produit']->getCentPrice()
                * $ligne['quantite'];
        }

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
        AjouterRepository $ajouterRepository,
        ProduitRepository $produitRepository,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        string $stripeSecretKey
    ): Response {
        $session = $request->getSession();
        $utilisateur = $this->getUser();

        if ($utilisateur instanceof Utilisateur) {
            $panier = $utilisateur->getPanier();

            if ($panier === null) {
                $this->addFlash(
                    'warning',
                    'Votre panier est vide.'
                );

                return $this->redirectToRoute('app_panier');
            }
        }

        $lineItems = [];

        if ($utilisateur instanceof Utilisateur){
            $lignes = $ajouterRepository->findBy([
                'panier' => $panier,
            ]);

            foreach ($lignes as $ligne){
                $produit = $ligne->getProduit();

                $quantite = $ligne->getQuantite();

                if ($produit === null || !$produit->isActif() || $quantite < 1 || $quantite > $produit->getStock()){
                    return $this->redirectToRoute('app_panier');
                }

                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $produit->getTitle(),
                        ],
                        'unit_amount' => $produit->getCentPrice(),
                    ],
                    'quantity' => $quantite,
                ];
            }
        } else {
            $panierSession = $session->get('panier', []);

            foreach ($panierSession as $produitId => $quantite) {
                $produit = $produitRepository->find($produitId);

                if ($produit === null) {
                    continue;
                }

                if(!is_int($quantite) || $quantite < 1 || $quantite > $produit->getStock() || !$produit->isActif()){
                    return $this->redirectToRoute('app_panier');
                }  

                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $produit->getTitle(),
                        ],
                        'unit_amount' => $produit->getCentPrice(),
                    ],
                    'quantity' => $quantite,
                ];
            }
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

            $stripe = new StripeClient($stripeSecretKey);

            try{
                $checkoutSession = $stripe->checkout->sessions->create([
                    'mode' => 'payment',

                    'line_items' => $lineItems,

                    'success_url' => $this->generateUrl(
                        'app_commande_paiement_success',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ) . '?session_id={CHECKOUT_SESSION_ID}',

                    'cancel_url' => $this->generateUrl(
                        'app_commande_paiement',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ]);

            } catch(ApiErrorException $e){
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
    public function paiementSuccess(Request $request, EntityManagerInterface $entityManager, PaiementRepository $paiementRepository, ProduitRepository $produitRepository, AdresseRepository $adresseRepository, #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        string $stripeSecretKey
    ): Response {
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

        $stripe = new StripeClient($stripeSecretKey);

        try{
            $checkoutSession = $stripe
                ->checkout
                ->sessions
                ->retrieve($stripeSessionId);
        } catch(ApiErrorException $e){
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

            // ICI
            $utilisateur = $this->getUser();

            if($utilisateur instanceof Utilisateur) {
                $panier = $utilisateur->getPanier();

                if($panier === null){
                    return $this->redirectToRoute('app_panier');

                
                }
            } 
            else 
            {
                $panierSession = $request->getSession()->get('panier', []);

                if ($panierSession === []){
                    return $this->redirectToRoute('app_panier');
                }
            }

            $adresseId = $request->getSession()->get('commande_adresse_id');
            $adresse = $adresseRepository->find($adresseId);

            if($adresse === null){
                return $this->redirectToRoute('app_commande_adresse');
            }

            if($utilisateur instanceof Utilisateur && $adresse->getUtilisateur() !== $utilisateur){
                throw $this->createAccessDeniedException('Adresse invalide.');
            }


            $commande = new Commande();
            $commande->setDateCommande(new \DateTime());
            $commande->setUtilisateur($utilisateur);
            $commande->setStatus('payee');
            $commande->setAdresse($adresse);

            $total = 0;

            if ($utilisateur instanceof Utilisateur){
                foreach($panier->getAjouters() as $ligne){
                    $produit = $ligne->getProduit();
                    $quantite = $ligne->getQuantite();

                    if($produit === null || !$produit->isActif() || $quantite < 1 || $quantite > $produit->getStock()){
                        return $this->redirectToRoute('app_panier');
                    }
                    $total += $ligne->getProduit()->getCentPrice() * $ligne->getQuantite();
                }

            } else {
                foreach ($panierSession as $produitId => $quantite){
                    $produit = $produitRepository->find($produitId);

                    if ($produit === null){
                        continue;
                    }

                    if(!is_int($quantite) || $quantite < 1 || $quantite > $produit->getStock() || !$produit->isActif()){
                        return $this->redirectToRoute('app_panier');
                    }
                    $total += $produit->getCentPrice() * $quantite;
                }
            }


            $commande->setMontantTotalCentimes($total);

            if ($checkoutSession->amount_total !== $total) {
                throw $this->createAccessDeniedException(
                    'Le montant payé ne correspond pas au montant de la commande.'
                );
            }

            $paiement = new Paiement();
            $paiement->setAmountincents($checkoutSession->amount_total);
            $paiement->setPaymentDate(new \DateTime());
            $paiement->setPaymentMethod('stripe');
            $paiement->setStatut($checkoutSession->payment_status);
            $paiement->setReferenceTransaction($checkoutSession->id);
            $paiement->setCommande($commande);

            $entityManager->persist($paiement);

            $entityManager->beginTransaction();

            try{
                if ($utilisateur instanceof Utilisateur){

                    foreach ($panier->getAjouters() as $ligne){
                        $produit = $produitRepository->findWithLock($ligne->getProduit()->getId());

                        if($produit === null || !$produit->isActif() || $ligne->getQuantite() > $produit->getStock()){
                            $entityManager->rollback();

                            $paymentIntentId = $checkoutSession->payment_intent;

                            if ($paymentIntentId !== null) {
                                $stripe->refunds->create([
                                    'payment_intent' => $paymentIntentId,
                                ]);
                            }

                            return $this->redirectToRoute('app_panier');
                        }

                        $produit->setStock($produit->getStock() - $ligne->getQuantite());

                        $contient = new Contient();
                        $contient->setCommande($commande);
                        $contient->setProduit($produit);
                        $contient->setQuantite($ligne->getQuantite());
                        $contient->setPrixUnitaireCentime(
                            $produit->getCentPrice()
                        );

                        $entityManager->persist($contient);


                    }

                } else {
                    foreach ($panierSession as $produitId => $quantite){
                        $produit = $produitRepository->findWithLock($produitId);

                        if($produit === null || !$produit->isActif() || !is_int($quantite) || $quantite < 1 || $quantite > $produit->getStock()){

                            $entityManager->rollback();

                            $paymentIntentId = $checkoutSession->payment_intent;

                            if ($paymentIntentId !== null) {
                                $stripe->refunds->create([
                                    'payment_intent' => $paymentIntentId,
                                ]);
                            }

                            return $this->redirectToRoute('app_panier');
                        }

                        $produit->setStock($produit->getStock() - $quantite);

                        $contient = new Contient();
                        $contient->setCommande($commande);
                        $contient->setProduit($produit);
                        $contient->setQuantite($quantite);
                        $contient->setPrixUnitaireCentime(
                            $produit->getCentPrice()
                        );

                        $entityManager->persist($contient);
                    }
                }

                $entityManager->persist($commande);
                if($utilisateur instanceof Utilisateur){
                    foreach($panier->getAjouters() as $ligne){
                        $entityManager->remove($ligne);
                    }

                } 
                
                $entityManager->flush();
                $entityManager->commit();
            } catch (\Throwable $e){
                $entityManager->rollback();
                throw $e;
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
    public function delete(Request $request, Commande $commande, EntityManagerInterface $entityManager, #[Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey): Response
    {
        $utilisateur = $this->getUser();

        if(!$utilisateur instanceof Utilisateur || $commande->getUtilisateur() !== $utilisateur){
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        if ($this->isCsrfTokenValid('delete'.$commande->getId(), $request->getPayload()->getString('_token'))) {
            $paiement = $commande->getPaiement();
            if ($paiement !== null && $paiement->getStatut() === 'paid'){
                $stripe = new StripeClient($stripeSecretKey);

                try {
                    $checkoutSession = $stripe->checkout->sessions->retrieve(
                        $paiement->getReferenceTransaction()
                    );

                    $paymentIntentId = $checkoutSession->payment_intent;

                    if ($paymentIntentId === null) {
                        throw $this->createNotFoundException(
                            'Aucun paiement Stripe associé à cette commande.'
                        );
                    }

                    $stripe->refunds->create([
                        'payment_intent' => $paymentIntentId,
                    ]);
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
