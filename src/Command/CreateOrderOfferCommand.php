<?php

namespace App\Command;

use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Repository\OrderRepository;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-order-offer',
    description: 'Create OrderOffer for existing Order',
)]
class CreateOrderOfferCommand extends Command
{
    public function __construct(
        private readonly OrderOfferCalculatorInterface $calculator,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('order-id', InputArgument::REQUIRED, 'Order UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $orderId = $input->getArgument('order-id');
        $order = $this->orderRepository->find($orderId);
        
        if (!$order) {
            $io->error('Order not found');
            return Command::FAILURE;
        }
        
        $io->info('Order: ' . $order->getDropoutAddress());
        $io->info('Coordinates: ' . $order->getDropoutLatitude() . ', ' . $order->getDropoutLongitude());
        
        if ($order->getOffers()->count() > 0) {
            $io->warning('Order already has ' . $order->getOffers()->count() . ' offer(s)');
            
            if (!$io->confirm('Delete existing offers and create new?', false)) {
                return Command::SUCCESS;
            }
            
            // Удаляем существующие
            foreach ($order->getOffers() as $offer) {
                $this->entityManager->remove($offer);
            }
            $this->entityManager->flush();
        }
        
        // Рассчитываем
        $result = $this->calculator->calculate($order);
        
        if (!$result->success) {
            $io->error('Calculation failed: ' . $result->errorCode);
            $io->warning($result->errorMessage);
            return Command::FAILURE;
        }
        
        // Создаем
        $orderOffer = new OrderOffer();
        $orderOffer->setRelatedOrder($order);
        $orderOffer->setBrutto($result->bruttoPrice);
        $orderOffer->setNetto($result->nettoPrice);
        $orderOffer->setVat($result->vatAmount);
        $orderOffer->setFee($result->feeAmount);
        $orderOffer->setStatus(OrderOffer::STATUS['ACCEPTED']);
        
        $this->entityManager->persist($orderOffer);
        $this->entityManager->flush();
        
        $io->success('OrderOffer created!');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $orderOffer->getId()->toRfc4122()],
                ['Brutto', $result->bruttoPrice . ' cents = ' . ($result->bruttoPrice / 100) . ' EUR'],
                ['Netto', $result->nettoPrice . ' cents = ' . ($result->nettoPrice / 100) . ' EUR'],
                ['Fee', $result->feeAmount . ' cents'],
                ['Fee rate %', (string) $result->feePercent],
                ['VAT rate %', (string) $result->vatPercent],
                ['VAT amount', $result->vatAmount . ' cents'],
            ]
        );
        
        return Command::SUCCESS;
    }
}
