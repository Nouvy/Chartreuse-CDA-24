<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\Payment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création des utilisateurs
        $user1 = new User();
        $user1->setEmail('user@example.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $user1->setRoles(['ROLE_USER']);
        $user1->setIsVerified(true);
        $manager->persist($user1);

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $manager->persist($admin);

        // Création des produits
        $products = [];
        $productData = [
            ['Smartphone', 'Un smartphone dernière génération', 499, 20, 50],
            ['Ordinateur portable', 'Ordinateur portable performant', 899, 20, 30],
            ['Tablette', 'Tablette tactile HD', 299, 20, 40],
            ['Casque audio', 'Casque audio sans fil', 99, 20, 100],
            ['Montre connectée', 'Montre connectée sport', 199, 20, 60],
        ];

        foreach ($productData as [$name, $description, $price, $vat, $stock]) {
            $product = new Product();
            $product->setName($name);
            $product->setDescription($description);
            $product->setHtPrice($price);
            $product->setVatRate($vat);
            $product->setStock($stock);
            $products[] = $product;
            $manager->persist($product);
        }

        // Création d'un panier
        $cart = new Cart();
        $cart->setUser($user1);
        $cart->setQuantity(2);
        $cart->addProduct($products[0]);
        $cart->addProduct($products[1]);
        $manager->persist($cart);

        // Première commande et paiement
        $payment1 = new Payment();
        $payment1->setAmount(798);
        $payment1->setStatus(true);
        $payment1->setPaymentMethod('carte');
        $manager->persist($payment1);

        $order1 = new Order();
        $order1->setUser($user1);
        $order1->setOrderDate(new \DateTime());
        $order1->setStatus(true);
        $order1->setQuantity(3);
        $order1->addProduct($products[0]);
        $order1->addProduct($products[2]);
        $order1->setPayment($payment1);
        $manager->persist($order1);

        // Deuxième commande et paiement
        $payment2 = new Payment();
        $payment2->setAmount(99);
        $payment2->setStatus(false);
        $payment2->setPaymentMethod('paypal');
        $manager->persist($payment2);

        $order2 = new Order();
        $order2->setUser($user1);
        $order2->setOrderDate(new \DateTime());
        $order2->setStatus(false);
        $order2->setQuantity(1);
        $order2->addProduct($products[3]);
        $order2->setPayment($payment2);
        $manager->persist($order2);

        $manager->flush();
    }
} 