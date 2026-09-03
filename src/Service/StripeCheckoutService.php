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
}