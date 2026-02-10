<?php

namespace App\Command;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-order-offer',
    description: 'Test OrderOffer calculation for a specific order',
)]
class TestOrderOfferCalculationCommand extends Command
{
    public function __construct(
        private readonly OrderOfferCalculatorInterface $calculator,
        private readonly OrderRepository $orderRepository,
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
            $io->error('Order not found: ' . $orderId);
            return Command::FAILURE;
        }
        
        $io->title('Testing OrderOffer Calculation');
        $io->section('Order Info');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $order->getId()->toRfc4122()],
                ['Dropout Address', $order->getDropoutAddress()],
                ['Latitude', $order->getDropoutLatitude() ?? 'NULL'],
                ['Longitude', $order->getDropoutLongitude() ?? 'NULL'],
                ['Cargo Count', $order->getCargo()->count()],
                ['Total Weight', $this->getTotalWeight($order) . ' kg'],
                ['Existing Offers', $order->getOffers()->count()],
            ]
        );
        
        $io->section('Calculation');
        $result = $this->calculator->calculate($order);
        
        if ($result->success) {
            $io->success('Calculation successful!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Brutto Price', $result->bruttoPrice . ' cents (' . ($result->bruttoPrice / 100) . ' EUR)'],
                    ['Netto Price', $result->nettoPrice . ' cents (' . ($result->nettoPrice / 100) . ' EUR)'],
                    ['VAT %', $result->vatPercent . '%'],
                ]
            );
        } else {
            $io->error('Calculation failed!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Error Code', $result->errorCode],
                    ['Error Message', $result->errorMessage],
                ]
            );
        }
        
        return Command::SUCCESS;
    }
    
    private function getTotalWeight(Order $order): int
    {
        $total = 0;
        foreach ($order->getCargo() as $cargo) {
            $total += ($cargo->getQuantity() ?? 0) * ($cargo->getWeightKg() ?? 0);
        }
        return $total;
    }
}
