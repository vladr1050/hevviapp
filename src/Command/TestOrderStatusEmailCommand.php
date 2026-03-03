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
use App\Repository\OrderRepository;
use App\Service\OrderStatus\OrderStatusService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-order-status-email',
    description: 'Тестовая команда для проверки отправки email уведомлений о статусах заказов',
)]
class TestOrderStatusEmailCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderStatusService $orderStatusService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::OPTIONAL, 'UUID заказа для тестирования')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Статус для тестирования (ACCEPTED, ASSIGNED, PICKUP_DONE, DELIVERED)')
            ->setHelp(<<<'HELP'
Эта команда позволяет протестировать отправку email уведомлений для заказов.

Использование:
  php bin/console app:test-order-status-email [order-id] --status=ACCEPTED

Если order-id не указан, будет использован первый заказ с нужным статусом.

Примеры:
  # Отправить уведомление для конкретного заказа
  php bin/console app:test-order-status-email a1b2c3d4-e5f6-7890-abcd-ef1234567890 --status=ACCEPTED
  
  # Отправить уведомление для первого заказа со статусом DELIVERED
  php bin/console app:test-order-status-email --status=DELIVERED
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $orderId = $input->getArgument('order-id');
        $statusName = $input->getOption('status');

        // Определяем статус
        if (!$statusName) {
            $io->error('Необходимо указать статус через опцию --status');
            return Command::FAILURE;
        }

        if (!isset(Order::STATUS[$statusName])) {
            $io->error(sprintf('Неизвестный статус: %s', $statusName));
            $io->note('Доступные статусы: ' . implode(', ', array_keys(Order::STATUS)));
            return Command::FAILURE;
        }

        $status = Order::STATUS[$statusName];

        // Получаем заказ
        if ($orderId) {
            $order = $this->orderRepository->find($orderId);
            if (!$order) {
                $io->error(sprintf('Заказ с ID %s не найден', $orderId));
                return Command::FAILURE;
            }
        } else {
            // Ищем первый заказ с нужным статусом
            $order = $this->orderRepository->findOneBy(['status' => $status]);
            if (!$order) {
                $io->error(sprintf('Не найдено заказов со статусом %s', $statusName));
                return Command::FAILURE;
            }
        }

        // Проверяем наличие отправителя
        if (!$order->getSender()) {
            $io->error('У заказа отсутствует отправитель');
            return Command::FAILURE;
        }

        // Выводим информацию о заказе
        $io->section('Информация о заказе');
        $io->table(
            ['Параметр', 'Значение'],
            [
                ['ID заказа', $order->getId()->toRfc4122()],
                ['Статус', $statusName . ' (' . $status . ')'],
                ['Отправитель', $order->getSender()->getFirstName() . ' ' . $order->getSender()->getLastName()],
                ['Email', $order->getSender()->getEmail()],
                ['Локаль', $order->getSender()->getLocale()],
                ['Адрес забора', $order->getPickupAddress()],
                ['Адрес доставки', $order->getDropoutAddress()],
            ]
        );

        // Подтверждение отправки
        if (!$io->confirm('Отправить email уведомление?', false)) {
            $io->info('Отправка отменена');
            return Command::SUCCESS;
        }

        // Отправляем email
        $io->info('Отправка email уведомления...');
        
        try {
            $this->orderStatusService->sendEmailToSender($order);
            $io->success('Email уведомление успешно отправлено!');
            $io->note('Проверьте логи для детальной информации: var/log/dev.log');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при отправке email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
