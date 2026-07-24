<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Repository\UtilisateurRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private readonly EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
            Request $request, 
            UserPasswordHasherInterface $userPasswordHasher, 
            EntityManagerInterface $entityManager
        ): Response{

        $user = new Utilisateur();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            $user->setIsVerified(false);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email', 
                $user
            );

            $this->addFlash(
                'success', 
                'Un email de confirmation a été envoyé à votre adresse email. Veuillez vérifier votre boîte de réception.'
            );

            // do anything else you need here, like send an email

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request, 
        UtilisateurRepository $utilisateurRepository, 
        EntityManagerInterface $entityManager
    ): Response {
        $id = $request->query->getInt('id');

        if($id === 0){
            $this->addFlash(
                'danger',
                'Le lien de confirmation est invalide.'
            );


            return $this->redirectToRoute('app_register');
        }

        $utilisateur = $utilisateurRepository->find($id);

        if(!$utilisateur){
            $this->addFlash(
                'danger',
                'Aucun utilisateur ne correspond à ce lien.'
            );

            return $this->redirectToRoute('app_register');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation(
                $request, 
                $utilisateur
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'danger', 
                $exception->getReason()
            );

            return $this->redirectToRoute('app_register');
        }

        $entityManager->flush();

        $this->addFlash(
            'success', 
            'Votre adresse email a été vérifiée avec succès.');

        return $this->redirectToRoute('app_login');

    }

    #[Route('/register/check-email', name: 'app_check_email')]
    public function checkEmail(): Response{
        
        return $this->render('registration/check_email.html.twig');
    }
}
