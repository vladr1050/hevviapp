<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Command;

use App\Entity\Order;
use App\Notification\NotificationEventKey;
use App\Repository\OrderRepository;
use App\Service\Notification\NotificationDispatchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-order-status-email',
    description: 'Test order status notifications via NotificationDispatchService (DB rules + Messenger).',
)]
class TestOrderStatusEmailCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::OPTIONAL, 'UUID заказа для тестирования')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'ACCEPTED, ASSIGNED, AWAITING_PICKUP, IN_TRANSIT, DELIVERED')
            ->setHelp(<<<'HELP'
Отправка тестового уведомления по правилам в БД (как в production).

Использование:
  php bin/console app:test-order-status-email [order-id] --status=ACCEPTED

При MESSENGER_TRANSPORT_DSN=sync:// обработка выполняется сразу в том же процессе.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orderId = $input->getArgument('order-id');
        $statusName = $input->getOption('status');

        if (!$statusName) {
            $io->error('Необходимо указать статус через опцию --status');

            return Command::FAILURE;
        }

        if (!isset(Order::STATUS[$statusName])) {
            $io->error(sprintf('Неизвестный статус: %s', $statusName));
            $io->note('Доступные статусы: '.implode(', ', array_keys(Order::STATUS)));

            return Command::FAILURE;
        }

        $status = Order::STATUS[$statusName];
        $eventKey = match ($status) {
            Order::STATUS['ACCEPTED'] => NotificationEventKey::ORDER_STATUS_CHANGED_TO_ACCEPTED,
            Order::STATUS['ASSIGNED'] => NotificationEventKey::ORDER_STATUS_CHANGED_TO_ASSIGNED,
            Order::STATUS['AWAITING_PICKUP'] => NotificationEventKey::ORDER_STATUS_CHANGED_TO_AWAITING_PICKUP,
            Order::STATUS['IN_TRANSIT'] => NotificationEventKey::ORDER_STATUS_CHANGED_TO_IN_TRANSIT,
            Order::STATUS['DELIVERED'] => NotificationEventKey::ORDER_STATUS_CHANGED_TO_DELIVERED,
            default => null,
        };

        if ($eventKey === null) {
            $io->error('Эта команда поддерживает только ACCEPTED, ASSIGNED, AWAITING_PICKUP, IN_TRANSIT, DELIVERED. Для счёта и перевозчика используйте реальные сценарии или app:notification:replay.');

            return Command::FAILURE;
        }

        if ($orderId) {
            $order = $this->orderRepository->find($orderId);
            if (!$order) {
                $io->error(sprintf('Заказ с ID %s не найден', $orderId));

                return Command::FAILURE;
            }
        } else {
            $order = $this->orderRepository->findOneBy(['status' => $status]);
            if (!$order) {
                $io->error(sprintf('Не найдено заказов со статусом %s', $statusName));

                return Command::FAILURE;
            }
        }

        if (!$order->getSender()) {
            $io->error('У заказа отсутствует отправитель');

            return Command::FAILURE;
        }

        $io->section('Информация о заказе');
        $io->table(
            ['Параметр', 'Значение'],
            [
                ['ID заказа', $order->getId()->toRfc4122()],
                ['Статус', $statusName.' ('.$status.')'],
                ['Событие', $eventKey],
                ['Отправитель', $order->getSender()->getFirstName().' '.$order->getSender()->getLastName()],
                ['Email', $order->getSender()->getEmail()],
                ['Локаль', $order->getSender()->getLocale()],
                ['Адрес забора', $order->getPickupAddress()],
                ['Адрес доставки', $order->getDropoutAddress()],
            ]
        );

        if (!$io->confirm('Отправить уведомление (правила из БД)?', false)) {
            $io->info('Отменено');

            return Command::SUCCESS;
        }

        $io->info('Диспетчеризация…');
        try {
            $this->notificationDispatchService->dispatch($order, $eventKey);
            $io->success('Событие отправлено в Messenger (или синхронно при sync://). Проверьте логи и notification_log.');
        } catch (\Throwable $e) {
            $io->error('Ошибка: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
