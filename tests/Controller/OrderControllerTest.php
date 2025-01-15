<?php

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $orderRepository;
    private string $path = '/order/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->orderRepository = $this->manager->getRepository(Order::class);

        foreach ($this->orderRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Order index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'order[order_date]' => 'Testing',
            'order[status]' => 'Testing',
            'order[quantity]' => 'Testing',
            'order[user]' => 'Testing',
            'order[payment]' => 'Testing',
            'order[products]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->orderRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Order();
        $fixture->setOrder_date('My Title');
        $fixture->setStatus('My Title');
        $fixture->setQuantity('My Title');
        $fixture->setUser('My Title');
        $fixture->setPayment('My Title');
        $fixture->setProducts('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Order');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Order();
        $fixture->setOrder_date('Value');
        $fixture->setStatus('Value');
        $fixture->setQuantity('Value');
        $fixture->setUser('Value');
        $fixture->setPayment('Value');
        $fixture->setProducts('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'order[order_date]' => 'Something New',
            'order[status]' => 'Something New',
            'order[quantity]' => 'Something New',
            'order[user]' => 'Something New',
            'order[payment]' => 'Something New',
            'order[products]' => 'Something New',
        ]);

        self::assertResponseRedirects('/order/');

        $fixture = $this->orderRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getOrder_date());
        self::assertSame('Something New', $fixture[0]->getStatus());
        self::assertSame('Something New', $fixture[0]->getQuantity());
        self::assertSame('Something New', $fixture[0]->getUser());
        self::assertSame('Something New', $fixture[0]->getPayment());
        self::assertSame('Something New', $fixture[0]->getProducts());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Order();
        $fixture->setOrder_date('Value');
        $fixture->setStatus('Value');
        $fixture->setQuantity('Value');
        $fixture->setUser('Value');
        $fixture->setPayment('Value');
        $fixture->setProducts('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/order/');
        self::assertSame(0, $this->orderRepository->count([]));
    }
}
