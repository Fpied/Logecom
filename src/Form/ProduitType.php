<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Categorie;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\CallbackTransformer;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Titre',
            ])
            ->add('description', null, [
                'label' => 'Description',
            ])
            ->add('centPrice' , NumberType::class, [
                'label' => 'Prix en euros',
                'scale' => 2,
                'html5' => true,
                'attr'  => [
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => 'Exemple: 19.99',
                ]
            ])
            ->add('stock', null, [
                'label' => 'Stock',
            ])
            ->add('state', null, [
                'label' => 'État',
            ])

            ->add('imageFiles', FileType::class, [
                'label' => 'Images',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new File([
                            'maxSize' => '5M',
                            'mimeTypes' => [
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                            ],
                            'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, GIF).',
                        ]),
                    ]),
                ],
            ])
            
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'autocomplete' => true,
                'attr' => [
                    'data-controller' => 'categorie',
                    'data-categorie-url-value' => '/categorie/new-ajax',
                ]
            ])
        ;

        $builder->get('centPrice')->addModelTransformer(
            new CallbackTransformer(
                fn (?int $centimes): ?float =>
                    $centimes === null ? null : $centimes / 100,

                fn($euros): ?int =>
                    $euros === null || $euros === ''
                        ? null
                        : (int) round((float)$euros * 100)
            ) 
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
