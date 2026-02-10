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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-order-history',
    description: 'Тестирование функционала OrderHistory',
)]
class TestOrderHistoryCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Находим первый заказ
        $order = $this->orderRepository->findOneBy([]);

        if (!$order) {
            $io->error('Не найдено ни одного заказа для тестирования');
            return Command::FAILURE;
        }

        $io->info(sprintf('Тестируем заказ: %s', $order));
        $io->info(sprintf('Текущий статус: %d', $order->getStatus()));
        $io->info(sprintf('Количество записей в истории до изменения: %d', $order->getHistories()->count()));

        // Сохраняем текущий статус
        $oldStatus = $order->getStatus();

        // Меняем статус
        $newStatus = $oldStatus === Order::STATUS['REQUEST'] 
            ? Order::STATUS['INVOICED'] 
            : Order::STATUS['REQUEST'];

        $order->setStatus($newStatus);

        $io->info(sprintf('Меняем статус на: %d', $newStatus));

        // Сохраняем изменения
        $this->entityManager->flush();

        // Обновляем данные из БД
        $this->entityManager->refresh($order);

        $io->success(sprintf('Статус изменен на: %d', $order->getStatus()));
        $io->success(sprintf('Количество записей в истории после изменения: %d', $order->getHistories()->count()));

        // Выводим последнюю запись истории
        $histories = $order->getHistories();
        if ($histories->count() > 0) {
            $lastHistory = $histories->last();
            $io->section('Последняя запись в истории:');
            $io->table(
                ['Поле', 'Значение'],
                [
                    ['ID', $lastHistory->getId()?->toRfc4122()],
                    ['Статус', $lastHistory->getStatus()],
                    ['Changed By', $lastHistory->getChangedBy()],
                    ['Meta', json_encode($lastHistory->getMeta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
                    ['Created At', $lastHistory->getCreatedAt()?->format('Y-m-d H:i:s')],
                ]
            );
        }

        return Command::SUCCESS;
    }
}
