<?php

namespace App\Controller;

use App\Entity\Message;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $message = new Message();

        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($message);
            $entityManager->flush();

            $email = (new Email())
                ->from('contact@logecom.local')
                ->to('admin@logecom.local')
                ->replyTo($message->getEmail())
                ->subject('Nouveau message reçu sur Logecom')
                ->text(
                    "Nom : {$message->getNom()}\n" .
                    "Prénom : {$message->getPrenom()}\n" .
                    "Email : {$message->getEmail()}\n" .
                    "Adresse postale : {$message->getAdressePostale()}\n\n" .
                    "Message :\n{$message->getContenu()}"
                );

            $mailer->send($email);

            $this->addFlash(
                'success',
                'Votre message a bien été envoyé.'
            );

            return $this->redirectToRoute('contact');
        }

        return $this->render('main/contact.html.twig', [
            'message' => $message,
            'form' => $form,
        ]);
    }
}