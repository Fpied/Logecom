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
use App\Repository\AdresseRepository;im
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Stripe\StripeClient;
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

        $commandes = $commandeRepository->findBy([
            'utilisateur' => $utilisateur,
        ]);


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
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $produit->getTitle(),
                        ],
                        'unit_amount' => $produit->getCentPrice(),
                    ],
                    'quantity' => $ligne->getQuantite(),
                ];
            }
        } else {
            $panierSession = $session->get('panier', []);

            foreach ($panierSession as $produitId => $quantite) {
                $produit = $produitRepository->find($produitId);

                if ($produit === null) {
                    continue;
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

            return $this->redirect($checkoutSession->url);
        }

        return $this->render('paiement/new.html.twig', [
            'selected_payment_method' => 'stripe',
        ]);
    }

    #[Route(
    '/paiement/success',
    name: 'app_commande_paiement_success',
    methods: ['GET']
    )]

    public function paiementSuccess(Request $request, EntityManagerInterface $entityManager, PaiementRepository $paiementRepository, ProduitRepository $produitRepository, #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        string $stripeSecretKey
    ): Response {
        $stripeSessionId = $request->query->get('session_id');

        if (!$stripeSessionId) {
            return $this->redirectToRoute('app_commande_paiement');
        }

        $stripe = new StripeClient($stripeSecretKey);

        $checkoutSession = $stripe
            ->checkout
            ->sessions
            ->retrieve($stripeSessionId);

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


            $commande = new Commande();
            $commande->setDateCommande(new \DateTime());
            $commande->setUtilisateur($utilisateur);
            $commande->setStatus('payee');

            $total = 0;

            if ($utilisateur instanceof Utilisateur){
                foreach($panier->getAjouters() as $ligne){
                    $total += $ligne->getProduit()->getCentPrice() * $ligne->getQuantite();
                }

            } else {
                foreach ($panierSession as $produitId => $quantite){
                    $produit = $produitRepository->find($produitId);

                    if ($produit === null){
                        continue;
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

            if ($utilisateur instanceof Utilisateur){

                foreach ($panier->getAjouters() as $ligne){
                    $contient = new Contient();
                    $contient->setCommande($commande);
                    $contient->setProduit($ligne->getProduit());
                    $contient->setQuantite($ligne->getQuantite());
                    $contient->setPrixUnitaireCentime(
                        $ligne->getProduit()->getCentPrice()
                    );

                    $entityManager->persist($contient);


                }

            } else {
                foreach ($panierSession as $produitId => $quantite){
                    $produit = $produitRepository->find($produitId);

                    if ($produit === null){
                        continue;
                    }

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

            } else {
                $request->getSession()->remove('panier');

            }
            
            $entityManager->flush();

            
        

        return $this->render('commande/confirmation.html.twig', [
            'stripe_session_id' => $checkoutSession->id,
        ]);
    }
    

    #[Route('/{id}', name: 'app_commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }
        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }


    #[Route('/{id}', name: 'app_commande_delete', methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        if ($this->isCsrfTokenValid('delete'.$commande->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($commande);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_commande_index', [], Response::HTTP_SEE_OTHER);
    }
}
