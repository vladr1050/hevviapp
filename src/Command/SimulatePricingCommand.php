<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CarrierRepository;
use App\Repository\OrderRepository;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pricing:simulate',
    description: 'Simulate delivery pricing for an order or raw coordinates/weight',
)]
final class SimulatePricingCommand extends Command
{
    public function __construct(
        private readonly OrderOfferCalculatorInterface $calculator,
        private readonly OrderRepository $orderRepository,
        private readonly CarrierRepository $carrierRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('order-id', null, InputOption::VALUE_REQUIRED, 'Order UUID')
            ->addOption('pickup-lat', null, InputOption::VALUE_REQUIRED, 'Pickup latitude')
            ->addOption('pickup-lng', null, InputOption::VALUE_REQUIRED, 'Pickup longitude')
            ->addOption('drop-lat', null, InputOption::VALUE_REQUIRED, 'Drop-off latitude')
            ->addOption('drop-lng', null, InputOption::VALUE_REQUIRED, 'Drop-off longitude')
            ->addOption('weight', null, InputOption::VALUE_REQUIRED, 'Total weight kg', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = $input->getOption('order-id');

        if ($orderId !== null) {
            $order = $this->orderRepository->find($orderId);
            if ($order === null) {
                $io->error('Order not found: '.$orderId);

                return Command::FAILURE;
            }
        } else {
            $io->error('Provide --order-id=UUID (coordinate-only simulation not implemented yet).');

            return Command::FAILURE;
        }

        $carrier = $order->getCarrier() ?? $this->carrierRepository->findDefaultForPricing();
        $io->title('Pricing simulation');
        $io->table(
            ['Field', 'Value'],
            [
                ['Order', $order->getReference() ?? $order->getId()?->toRfc4122()],
                ['Carrier', $carrier?->getLegalName() ?? '—'],
                ['Algorithm', $carrier?->getPricingAlgorithm()->value ?? 'flat_by_drop_off_zone (legacy)'],
                ['Pickup', sprintf('%s, %s', $order->getPickupLatitude(), $order->getPickupLongitude())],
                ['Drop-off', sprintf('%s, %s', $order->getDropoutLatitude(), $order->getDropoutLongitude())],
            ],
        );

        $result = $this->calculator->calculate($order);
        if (!$result->success) {
            $io->error(sprintf('%s (%s)', $result->errorMessage, $result->errorCode));

            return Command::FAILURE;
        }

        $io->success('Calculation OK');
        $io->table(
            ['', 'Cents', 'Display'],
            [
                ['Currency', $result->currency, ''],
                ['Netto', (string) $result->nettoPrice, $this->eur($result->nettoPrice)],
                ['Fee', (string) $result->feeAmount, $this->eur($result->feeAmount)],
                ['VAT', (string) $result->vatAmount, $this->eur($result->vatAmount)],
                ['Brutto', (string) $result->bruttoPrice, $this->eur($result->bruttoPrice)],
            ],
        );

        return Command::SUCCESS;
    }

    private function eur(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return sprintf('%.2f EUR', $cents / 100);
    }
}
