<?php



namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProduitRepository;

final class MainController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        $produits = $produitRepository->findBy(['actif' => true], ['id' => 'DESC'], 10);

        return $this->render('main/index.html.twig', [
            'produits' => $produits,
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
