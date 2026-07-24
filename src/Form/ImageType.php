<?php

namespace App\Form;

use App\Entity\Image;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Choisir une image',
                'mapped' => false,
                'required' => $options['image_required'],
            ])
            ->add('main_image', null, [
                'label' => 'Image principale',
                'required' => false,
            ])
            ->add('orderImage', null, [
                'label' => "Ordre d'affichage",
            ])
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'title',
                'label' => 'Produit',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Image::class,
            'image_required' => true,
        ]);

        $resolver->setAllowedTypes('image_required', 'bool');
    }
}
