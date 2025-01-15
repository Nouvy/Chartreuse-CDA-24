<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findBy([], ['id' => 'DESC'], 4); // Récupère les 4 derniers produits

        return $this->render('home/index.html.twig', [
            'products' => $products,
        ]);
    }
}
