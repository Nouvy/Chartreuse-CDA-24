<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Product;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartUserController extends AbstractController
{
    #[Route('/mon-panier', name: 'app_cart_user')]
    public function index(CartRepository $cartRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $cart = $cartRepository->findOneBy(['user' => $user]);

        // Calcul du total HT et TTC
        $totalHT = 0;
        $totalTTC = 0;
        if ($cart) {
            foreach ($cart->getProducts() as $product) {
                $totalHT += $product->getHtPrice();
                $totalTTC += $product->getHtPrice() * (1 + $product->getVatRate() / 100);
            }
        }

        return $this->render('cart_user/index.html.twig', [
            'cart' => $cart,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC,
        ]);
    }

    #[Route('/add-to-cart/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function addToCart(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager,
        CartRepository $cartRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté pour ajouter des produits au panier');

            return $this->redirectToRoute('app_login');
        }

        // Vérifier le stock
        if ($product->getStock() <= 0) {
            $this->addFlash('danger', 'Produit en rupture de stock');

            return $this->redirectToRoute('app_home');
        }

        // Récupérer ou créer le panier
        $cart = $cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $cart->setQuantity(0);
        }

        // Ajouter le produit au panier
        if (!$cart->getProducts()->contains($product)) {
            $cart->addProduct($product);
            $cart->setQuantity($cart->getQuantity() + 1);

            // Mettre à jour le stock
            $product->setStock($product->getStock() - 1);

            $entityManager->persist($cart);
            $entityManager->flush();

            $this->addFlash('success', 'Produit ajouté au panier');
        } else {
            $this->addFlash('warning', 'Ce produit est déjà dans votre panier');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/remove-from-cart/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function removeFromCart(
        Product $product,
        EntityManagerInterface $entityManager,
        CartRepository $cartRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté');

            return $this->redirectToRoute('app_login');
        }

        $cart = $cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            $this->addFlash('danger', 'Panier non trouvé');

            return $this->redirectToRoute('app_home');
        }

        if ($cart->getProducts()->contains($product)) {
            $cart->removeProduct($product);
            $cart->setQuantity($cart->getQuantity() - 1);

            // Remettre le produit en stock
            $product->setStock($product->getStock() + 1);

            $entityManager->flush();

            $this->addFlash('success', 'Produit retiré du panier');
        } else {
            $this->addFlash('danger', 'Produit non trouvé dans le panier');
        }

        return $this->redirectToRoute('app_cart_user');
    }
}
