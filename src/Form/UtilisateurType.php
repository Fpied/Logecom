<?php

namespace App\Form;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('firstname')
            ->add('email')
            ->add('password')
            ->add('RegistrationDate', null, [
                'widget' => 'single_text',
            ])
            ->add('pseudo')
            ->add('photo_profil')
            ->add('role')
            ->add('panier', EntityType::class, [
                'class' => Panier::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
