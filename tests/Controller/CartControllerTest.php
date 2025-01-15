<?php

namespace App\Tests\Controller;

use App\Entity\Cart;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartControllerTest extends WebTestCase{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $cartRepository;
    private string $path = '/cart/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->cartRepository = $this->manager->getRepository(Cart::class);

        foreach ($this->cartRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Cart index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'cart[quantity]' => 'Testing',
            'cart[user]' => 'Testing',
            'cart[products]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->cartRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Cart();
        $fixture->setQuantity('My Title');
        $fixture->setUser('My Title');
        $fixture->setProducts('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Cart');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Cart();
        $fixture->setQuantity('Value');
        $fixture->setUser('Value');
        $fixture->setProducts('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'cart[quantity]' => 'Something New',
            'cart[user]' => 'Something New',
            'cart[products]' => 'Something New',
        ]);

        self::assertResponseRedirects('/cart/');

        $fixture = $this->cartRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getQuantity());
        self::assertSame('Something New', $fixture[0]->getUser());
        self::assertSame('Something New', $fixture[0]->getProducts());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Cart();
        $fixture->setQuantity('Value');
        $fixture->setUser('Value');
        $fixture->setProducts('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/cart/');
        self::assertSame(0, $this->cartRepository->count([]));
    }
}
