<?php



namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProduitRepository;

final class MainController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(ProduitRepository $produitRepository): Response
    {
        $produit = $produitRepository->findOneBy([], ['id' => 'ASC']);

        return $this->render('main/index.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/presentation', name: 'presentation')]
    public function presentation(): Response
    {
        return $this->render('main/presentation.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('main/contact.html.twig');

    }

    #[Route('/connexion', name:'connexion')]
    public function connexion(): Response
    {
        return $this->render('main/connexion.html.twig');
    }
}
