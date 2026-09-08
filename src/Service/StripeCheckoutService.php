<?php

namespace App\Service;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Stripe\Checkout\Session;

class StripeCheckoutService
{
    private StripeClient $stripeClient;

    public function __construct(#[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(STRIPE_SECRET_KEY)%')] string $stripeSecretKey)
    {
        $this->stripeClient = new StripeClient($stripeSecretKey);
    }

    /**
     * Crée une session de paiement Stripe Checkout.
     *
     * @param array $lineItems Les articles à inclure dans la session de paiement.
     * @param string $successUrl L'URL de redirection en cas de succès.
     * @param string $cancelUrl L'URL de redirection en cas d'annulation.
     * @return Session La session de paiement créée.
     * @throws ApiErrorException Si une erreur se produit lors de la création de la session.
     */
    public function createCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl): Session
    {
        return $this->stripeClient->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function recupererSession(string $sessionId): Session
    {
        return $this->stripeClient->checkout->sessions->retrieve($sessionId);
    }

    public function rembourser(string $paymentIntentId): void
    {
        $this->stripeClient->refunds->create([
            'payment_intent' => $paymentIntentId,
        ]);
    }

    public function creerTransfert(string $stripeAccountId, int $montantCentimes, string $paymentIntentId): \Stripe\Transfer
    {
        return $this->stripeClient->transfers->create([
            'amount' => $montantCentimes,
            'currency' => 'eur',
            'destination' => $stripeAccountId,
            'source_transaction' => $paymentIntentId,
        ]);
    }

    public function creerCompteConnecte(string $email): \Stripe\V2\Core\Account
    {
        return $this->stripeClient->v2->core->accounts->create([
            'contact_email' => $email,
            'display_name'=> $email,
            'identity'=> [
                'country' => 'FR',
            ],
            'dashboard' => 'none',
            'defaults' => [
                'responsibilities' => [
                    'fees_collector' => 'application',
                    'losses_collector' => 'application',
                ],
            ],
            'configuration' => [
                'recipient' => [
                    'capabilities' => [
                        'stripe_balance' => [
                            'stripe_transfers' => [
                                'requested' => true,

                            ],
                            
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function creerLienOnboarding(string $accountId, string $refreshUrl, string $returnUrl): \Stripe\V2\Core\AccountLink
    {
        return $this->stripeClient->v2->core->accountLinks->create([
            'account' => $accountId,
            'use_case' => [
                'type' => 'account_onboarding',
                'account_onboarding' => [
                    'configurations' => ['recipient'],
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                ],
            ],
        ]);
    }
}