<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Message\Notification\NotificationEventMessage;
use App\Notification\NotificationEventKey;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Service\Notification\NotificationRuleProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:notification:replay',
    description: 'Re-dispatch a notification event for an order (DB rules; send-once unless --force).',
)]
final class ReplayNotificationCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly NotificationRuleProcessor $processor,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, 'Order UUID')
            ->addArgument('event-key', InputArgument::REQUIRED, 'One of: '.implode(', ', NotificationEventKey::all()))
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Run processor in-process (skip Messenger)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore send-once (still creates new log rows)')
            ->addOption('invoice-id', null, InputOption::VALUE_REQUIRED, 'Invoice UUID for ORDER_PRICE_CONFIRMED (optional: latest invoice with PDF for order)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $id = Uuid::fromString((string) $input->getArgument('order-id'));
        } catch (\InvalidArgumentException) {
            $io->error('Invalid order UUID.');

            return Command::FAILURE;
        }

        $eventKey = (string) $input->getArgument('event-key');
        if (!in_array($eventKey, NotificationEventKey::all(), true)) {
            $io->error('Unknown event key. Use: '.implode(', ', NotificationEventKey::all()));

            return Command::FAILURE;
        }

        $order = $this->orderRepository->find($id);
        if ($order === null) {
            $io->error('Order not found.');

            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        $invoice = $this->resolveInvoiceForReplay($order, $eventKey, (string) ($input->getOption('invoice-id') ?? ''));
        if ($invoice === false) {
            $io->error('Invalid or missing invoice for --invoice-id.');

            return Command::FAILURE;
        }

        if ($eventKey === NotificationEventKey::ORDER_PRICE_CONFIRMED && $invoice === null) {
            $io->warning('ORDER_PRICE_CONFIRMED: no invoice with PDF found; rule may fail. Pass --invoice-id=UUID or generate PDF first.');
        }

        if ($input->getOption('sync')) {
            $result = $this->processor->process($order, $eventKey, $invoice, $force);
            $io->success(sprintf(
                'Done (sync). sent=%d failed=%d skipped=%d rules=%d',
                $result->getSentCount(),
                $result->getFailedCount(),
                $result->getSkippedCount(),
                $result->getMatchedRuleCount()
            ));

            return Command::SUCCESS;
        }

        $oid = $order->getId();
        if ($oid === null) {
            $io->error('Order has no id.');

            return Command::FAILURE;
        }

        $this->bus->dispatch(new NotificationEventMessage(
            $oid->toRfc4122(),
            $eventKey,
            $invoice?->getId()?->toRfc4122(),
            $force,
        ));
        $io->success('Message dispatched to Messenger (see MESSENGER_TRANSPORT_DSN; sync:// runs inline).');

        return Command::SUCCESS;
    }

    /**
     * @return Invoice|null|false null = no invoice needed or optional missing; false = invalid --invoice-id
     */
    private function resolveInvoiceForReplay(Order $order, string $eventKey, string $invoiceIdOpt): Invoice|false|null
    {
        if ($eventKey !== NotificationEventKey::ORDER_PRICE_CONFIRMED) {
            return null;
        }

        if ($invoiceIdOpt !== '') {
            try {
                $invId = Uuid::fromString($invoiceIdOpt);
            } catch (\InvalidArgumentException) {
                return false;
            }
            $invoice = $this->invoiceRepository->find($invId);
            if ($invoice === null) {
                return false;
            }

            return $invoice;
        }

        return $this->invoiceRepository->findLatestWithPdfForOrder($order);
    }
}
