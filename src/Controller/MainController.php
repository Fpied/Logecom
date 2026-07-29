<?php



namespace App\Controller;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProduitRepository;

final class MainController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        $produits = $produitRepository->findBy(
            ['actif' => true],
            ['id' => 'DESC'],
            10
        );

        $lignesPanier = [];

        $utilisateur = $this->getUser();

        if ($utilisateur instanceof Utilisateur) {
            $panier = $utilisateur->getPanier();

            if ($panier !== null) {
                $lignesPanier = $panier->getAjouters();
            }
        }

        return $this->render('main/index.html.twig', [
            'produits' => $produits,
            'lignesPanier' => $lignesPanier,
        ]);
    }

    #[Route('/presentation', name: 'presentation')]
    public function presentation(): Response
    {
        return $this->render('main/presentation.html.twig');
    }

 

    #[Route('/connexion', name:'connexion')]
    public function connexion(): Response
    {
        return $this->render('main/connexion.html.twig');
    }
}
