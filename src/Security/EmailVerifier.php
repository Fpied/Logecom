<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
    )
    {
        
    }

    public function sendEmailConfirmation(string $verifyEmailRouteName, Utilisateur $utilisateur): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string)$utilisateur->getId(),
            (string)$utilisateur->getEmail(),
            [
                'id' => $utilisateur->getId(),
            ]
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@logecom.fr', 'Logecom'))
            ->to((string)$utilisateur->getEmail())
            ->subject('Confirmez votre compte Logecom')
            ->htmlTemplate('registration/confirmation_email.html.twig')
            ->context([
                'utilisateur' => $utilisateur,
                'signedUrl' => $signatureComponents->getSignedUrl(),
                'expiresAtMessageKey' => $signatureComponents->getExpirationMessageKey(),
                'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);

    }

    public function handleEmailConfirmation(Request $request, Utilisateur $utilisateur): void
    {
        $this->verifyEmailHelper->validateEmailConfirmation($request->getUri(), (string)$utilisateur->getId(), (string)$utilisateur->getEmail());

        // Mark the user as verified
        $utilisateur->setIsVerified(true);
    }
}