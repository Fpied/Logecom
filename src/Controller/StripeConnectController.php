<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\StripeCheckoutService;

#[Route('/vendeur/stripe')]
#[IsGranted('ROLE_USER')]
class StripeConnectController extends AbstractController
{
    #[Route('/connexion', name: 'app_stripe_connect')]
    public function connecter(
        StripeCheckoutService $stripeCheckoutService,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        if ($utilisateur->getStripeAccountId() === null) {
            $account = $stripeCheckoutService->creerCompteConnecte($utilisateur->getEmail());
            $utilisateur->setStripeAccountId($account->id);
            $entityManager->flush();
        }

        $accountLink = $stripeCheckoutService->creerLienOnboarding(
            $utilisateur->getStripeAccountId(),
            $this->generateUrl('app_stripe_connect', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('app_stripe_connect_retour', [], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        return $this->redirect($accountLink->url);
    }

    #[Route('/retour', name: 'app_stripe_connect_retour')]
    public function retour(): Response
    {
        $this->addFlash('success', 'Configuration Stripe terminée.');
        return $this->redirectToRoute('home');
    }
}