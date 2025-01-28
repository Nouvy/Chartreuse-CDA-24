<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Repository\UserRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class OrderStateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private PaymentRepository $paymentRepository,
        private ProductRepository $productRepository,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        if (!$data instanceof Order) {
            throw new \RuntimeException('Data must be an instance of Order');
        }

        // Vérifier et récupérer l'utilisateur
        $user = $this->userRepository->find($data->getUserId());
        if (!$user) {
            throw new BadRequestHttpException('User not found');
        }

        // Vérifier et récupérer le paiement
        $payment = $this->paymentRepository->find($data->getPaymentId());
        if (!$payment) {
            throw new BadRequestHttpException('Payment not found');
        }

        // Vérifier et récupérer les produits
        $products = [];
        foreach ($data->getProductIds() as $productId) {
            $product = $this->productRepository->find($productId);
            if (!$product) {
                throw new BadRequestHttpException(sprintf('Product %d not found', $productId));
            }
            $products[] = $product;
        }

        // Créer la commande
        $order = new Order();
        $order->setUser($user);
        $order->setPayment($payment);
        $order->setStatus(true);
        $order->setQuantity(count($products));

        // Ajouter les produits
        foreach ($products as $product) {
            $order->addProduct($product);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
} 