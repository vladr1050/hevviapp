<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Command;

use App\Entity\Carrier;
use App\Entity\Manager;
use App\Entity\Order;
use App\Entity\OrderAssignment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-assignment-subscriber',
    description: 'Тестирует работу OrderAssignmentSubscriber'
)]
class TestOrderAssignmentSubscriberCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Тест OrderAssignmentSubscriber');

        // Шаг 1: Найти тестовые данные
        $io->section('Шаг 1: Поиск тестовых данных');

        $user = $this->em->getRepository(User::class)->findOneBy([]);
        $carrier = $this->em->getRepository(Carrier::class)->findOneBy([]);
        $manager = $this->em->getRepository(Manager::class)->findOneBy([]);

        if (!$user || !$carrier || !$manager) {
            $io->error('Недостаточно данных для теста. Необходимы: User, Carrier, Manager');
            return Command::FAILURE;
        }

        $io->success('Найдены тестовые данные:');
        $io->listing([
            'User: ' . $user->getEmail(),
            'Carrier: ' . $carrier->getLegalName(),
            'Manager: ' . $manager->getFullName(),
        ]);

        // Шаг 2: Создать заказ без carrier
        $io->section('Шаг 2: Создание заказа без carrier');

        $order = new Order();
        $order->setSender($user);
        $order->setStatus(Order::STATUS['OFFERED']);
        $order->setPickupAddress('Test Pickup Address, 123');
        $order->setDropoutAddress('Test Dropout Address, 456');
        $order->setCarrier(null); // Важно: carrier = null

        $this->em->persist($order);
        $this->em->flush();

        $io->success('Заказ создан: ' . $order->getId()->toRfc4122());
        $io->info('Carrier в Order: ' . ($order->getCarrier()?->getLegalName() ?? 'NULL ✓'));

        // Шаг 3: Создать назначение со статусом ASSIGNED
        $io->section('Шаг 3: Создание назначения со статусом ASSIGNED');

        $assignment = new OrderAssignment();
        $assignment->setRelatedOrder($order);
        $assignment->setCarrier($carrier);
        $assignment->setAssignedBy($manager);
        $assignment->setStatus(OrderAssignment::STATUS['ASSIGNED']);

        $this->em->persist($assignment);
        $this->em->flush();

        $io->success('Назначение создано: ' . $assignment->getId()->toRfc4122());
        $io->info('Carrier в Order: ' . ($order->getCarrier()?->getLegalName() ?? 'NULL ✓'));
        $io->warning('Carrier НЕ должен быть установлен (статус ASSIGNED)');

        // Шаг 4: Изменить статус на ACCEPTED
        $io->section('Шаг 4: Изменение статуса на ACCEPTED (триггер subscriber)');

        $io->caution('Меняем статус на ACCEPTED...');
        $assignment->setStatus(OrderAssignment::STATUS['ACCEPTED']);
        $this->em->flush();

        // Обновляем Order из БД
        $this->em->refresh($order);

        $io->newLine();
        $io->success('Статус изменен на ACCEPTED');

        // Шаг 5: Проверка результата
        $io->section('Шаг 5: Проверка результата');

        $orderCarrier = $order->getCarrier();

        if ($orderCarrier && $orderCarrier->getId()->equals($carrier->getId())) {
            $io->success('✅ ТЕСТ ПРОЙДЕН!');
            $io->listing([
                'Carrier в Order: ' . $orderCarrier->getLegalName(),
                'Ожидаемый Carrier: ' . $carrier->getLegalName(),
                'ID совпадают: ✓',
            ]);
        } else {
            $io->error('❌ ТЕСТ ПРОВАЛЕН!');
            $io->listing([
                'Carrier в Order: ' . ($orderCarrier?->getLegalName() ?? 'NULL'),
                'Ожидаемый Carrier: ' . $carrier->getLegalName(),
            ]);
            return Command::FAILURE;
        }

        // Шаг 6: Очистка (опционально)
        $io->section('Шаг 6: Очистка тестовых данных');

        $this->em->remove($assignment);
        $this->em->remove($order);
        $this->em->flush();

        $io->success('Тестовые данные удалены');

        return Command::SUCCESS;
    }
}
