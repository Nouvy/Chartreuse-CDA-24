<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/payment')]
class PaymentUserController extends AbstractController
{
    #[Route('/', name: 'app_payment_user')]
    public function index(CartRepository $cartRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $cart = $cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->getProducts()->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide');

            return $this->redirectToRoute('app_cart_user');
        }

        // Calcul du total
        $total = 0;
        foreach ($cart->getProducts() as $product) {
            $total += $product->getHtPrice() * (1 + $product->getVatRate() / 100);
        }

        return $this->render('payment_user/index.html.twig', [
            'cart' => $cart,
            'total' => $total,
        ]);
    }

    #[Route('/checkout', name: 'app_payment_user_checkout', methods: ['POST'])]
    public function checkout(Request $request, CartRepository $cartRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Validation des données du formulaire
        $fullName = $request->request->get('fullName');
        $address = $request->request->get('address');
        $zipCode = $request->request->get('zipCode');
        $city = $request->request->get('city');
        $phone = $request->request->get('phone');

        if (!$fullName || !$address || !$zipCode || !$city || !$phone) {
            $this->addFlash('error', 'Veuillez remplir tous les champs de l\'adresse de livraison');

            return $this->redirectToRoute('app_payment_user');
        }

        // Créer une nouvelle adresse
        $shippingAddress = new Address();
        $shippingAddress->setFullName($fullName);
        $shippingAddress->setAddress($address);
        $shippingAddress->setZipCode($zipCode);
        $shippingAddress->setCity($city);
        $shippingAddress->setPhone($phone);
        $shippingAddress->setUser($user);

        $entityManager->persist($shippingAddress);

        $cart = $cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->getProducts()->isEmpty()) {
            return $this->redirectToRoute('app_cart_user');
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $lineItems = [];
        foreach ($cart->getProducts() as $product) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $product->getName(),
                        'description' => $product->getDescription(),
                    ],
                    'unit_amount' => (int) ($product->getHtPrice() * (1 + $product->getVatRate() / 100) * 100),
                ],
                'quantity' => 1,
            ];
        }

        $checkoutSession = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->generateUrl('app_payment_user_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->generateUrl('app_payment_user_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'customer_email' => $user->getEmail(),
            'metadata' => [
                'address_id' => $shippingAddress->getId(),
            ],
        ]);

        $entityManager->flush();
        $url = $checkoutSession->url;

        return $this->redirect($url);
    }

    #[Route('/success', name: 'app_payment_user_success')]
    public function success(Request $request): Response
    {
        // On vérifie juste que l'utilisateur est connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // On affiche simplement la page de succès
        return $this->render('payment_user/success.html.twig');
    }

    #[Route('/cancel', name: 'app_payment_user_cancel')]
    public function cancel(): Response
    {
        return $this->render('payment_user/cancel.html.twig');
    }

    #[Route('/webhook', name: 'app_payment_user_webhook', methods: ['POST'])]
    public function webhook(Request $request, CartRepository $cartRepository, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $logger->info('Webhook called');

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        $payload = @file_get_contents('php://input');
        $sig_header = $request->headers->get('stripe-signature');

        $logger->info('Payload received', ['payload' => $payload]);

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
            $logger->info('Event constructed successfully', ['type' => $event->type]);
        } catch (\UnexpectedValueException $e) {
            $logger->error('Invalid payload', ['error' => $e->getMessage()]);

            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $logger->error('Invalid signature', ['error' => $e->getMessage()]);

            return new Response('Invalid signature', 400);
        }

        if ('checkout.session.completed' === $event->type) {
            $session = $event->data->object;
            $logger->info('Processing checkout session', ['session_id' => $session->id]);

            try {
                // Récupérer l'utilisateur via l'email du client
                $userRepository = $entityManager->getRepository(User::class);
                $user = $userRepository->findOneBy(['email' => $session->customer_email]);

                if (!$user) {
                    $logger->error('User not found', ['email' => $session->customer_email]);

                    return new Response('User not found', 404);
                }

                // Récupérer le panier
                $cart = $cartRepository->findOneBy(['user' => $user]);
                if (!$cart) {
                    $logger->error('Cart not found', ['user_id' => $user->getId()]);

                    return new Response('Cart not found', 404);
                }

                // Créer la commande
                $order = new Order();
                $order->setUser($user);
                $order->setOrderDate(new \DateTime());
                $order->setStatus(true);
                $order->setQuantity($cart->getQuantity());

                // Calculer le montant total
                $totalAmount = 0;
                foreach ($cart->getProducts() as $product) {
                    $order->addProduct($product);
                    $totalAmount += $product->getHtPrice() * (1 + $product->getVatRate() / 100);
                }

                // Créer le paiement
                $payment = new Payment();
                $payment->setAmount((int) ($totalAmount * 100));
                $payment->setStatus(true);
                $payment->setPaymentMethod('card');
                $entityManager->persist($payment);

                $order->setPayment($payment);
                $entityManager->persist($order);

                // Vider le panier
                foreach ($cart->getProducts() as $product) {
                    $cart->removeProduct($product);
                }
                $cart->setQuantity(0);

                // Sauvegarder les changements
                $entityManager->flush();

                $logger->info('Order created successfully', [
                    'order_id' => $order->getId(),
                    'user_id' => $user->getId(),
                ]);
            } catch (\Exception $e) {
                $logger->error('Error processing webhook', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return new Response('Internal error', 500);
            }
        }

        return new Response('Webhook processed successfully', 200);
    }
}
