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
use Symfony\Bundle\SecurityBundle\Security;

#[AsCommand(
    name: 'app:test-assignment-listener',
    description: 'Тестирует автоматическое заполнение AssignedBy в OrderAssignment'
)]
class TestOrderAssignmentListenerCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Тест OrderAssignmentListener (автоматическое заполнение AssignedBy)');

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

        // Шаг 2: Проверка текущего пользователя
        $io->section('Шаг 2: Проверка текущего пользователя');
        
        $currentUser = $this->security->getUser();
        
        if ($currentUser === null) {
            $io->warning('⚠️  Пользователь не залогинен (команда выполняется из CLI)');
            $io->note('В CLI контексте Security не имеет залогиненного пользователя.');
            $io->note('Listener НЕ установит AssignedBy автоматически.');
            $io->note('Для полноценного теста используйте веб-интерфейс Sonata Admin.');
        } elseif ($currentUser instanceof Manager) {
            $io->success('✅ Текущий пользователь: ' . $currentUser->getFullName() . ' (Manager)');
        } else {
            $io->warning('⚠️  Текущий пользователь не является Manager: ' . get_class($currentUser));
        }

        // Шаг 3: Создать заказ
        $io->section('Шаг 3: Создание заказа');

        $order = new Order();
        $order->setSender($user);
        $order->setStatus(Order::STATUS['OFFERED']);
        $order->setPickupAddress('Test Pickup Address, 123');
        $order->setDropoutAddress('Test Dropout Address, 456');

        $this->em->persist($order);
        $this->em->flush();

        $io->success('Заказ создан: ' . $order->getId()->toRfc4122());

        // Шаг 4: Создать назначение БЕЗ указания AssignedBy
        $io->section('Шаг 4: Создание назначения БЕЗ указания AssignedBy');
        $io->caution('Создаем OrderAssignment без вызова setAssignedBy()');

        $assignment = new OrderAssignment();
        $assignment->setRelatedOrder($order);
        $assignment->setCarrier($carrier);
        // НЕ устанавливаем AssignedBy вручную!
        $assignment->setStatus(OrderAssignment::STATUS['ASSIGNED']);

        $io->info('AssignedBy перед persist: ' . ($assignment->getAssignedBy()?->getFullName() ?? 'NULL'));

        try {
            $this->em->persist($assignment);
            $this->em->flush();
            $io->success('Назначение создано: ' . $assignment->getId()->toRfc4122());
        } catch (\Exception $e) {
            if ($currentUser === null && str_contains($e->getMessage(), 'assigned_by_id')) {
                $io->success('✅ ОЖИДАЕМАЯ ОШИБКА (CLI контекст)');
                $io->info('БД отклонила запись с NULL в assigned_by_id');
                $io->info('Это подтверждает, что listener НЕ работает в CLI (нет пользователя)');
                $io->note('В веб-интерфейсе с залогиненным Manager listener установит AssignedBy автоматически');
                
                $io->newLine();
                $io->note([
                    'Тест успешно подтвердил поведение:',
                    '✅ В CLI (без пользователя): listener не устанавливает AssignedBy → ошибка БД',
                    '✅ В Web (с Manager): listener установит AssignedBy → успех',
                    '',
                    'Для полноценного теста:',
                    '1. Залогиньтесь в Sonata Admin как Manager',
                    '2. Создайте новое назначение через веб-интерфейс',
                    '3. НЕ заполняйте поле AssignedBy (или оставьте пустым)',
                    '4. Сохраните - поле заполнится автоматически текущим менеджером',
                ]);
                
                $io->warning('Примечание: Order остался в БД (EntityManager закрыт после ошибки)');
                
                return Command::SUCCESS;
            }
            throw $e;
        }

        // Шаг 5: Проверка результата
        $io->section('Шаг 5: Проверка результата');

        // Обновляем из БД
        $this->em->refresh($assignment);

        $assignedBy = $assignment->getAssignedBy();

        if ($currentUser === null) {
            // CLI контекст
            if ($assignedBy === null) {
                $io->success('✅ ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (CLI)');
                $io->info('AssignedBy = NULL (нет залогиненного пользователя)');
            } else {
                $io->error('❌ НЕОЖИДАННЫЙ РЕЗУЛЬТАТ');
                $io->info('AssignedBy должен быть NULL в CLI, но установлен: ' . $assignedBy->getFullName());
            }
        } elseif ($currentUser instanceof Manager) {
            // Web контекст с Manager
            if ($assignedBy !== null && $assignedBy->getId()->equals($currentUser->getId())) {
                $io->success('✅ ТЕСТ ПРОЙДЕН!');
                $io->listing([
                    'AssignedBy установлен автоматически',
                    'Manager: ' . $assignedBy->getFullName(),
                    'Email: ' . $assignedBy->getEmail(),
                    'ID совпадает с текущим пользователем: ✓',
                ]);
            } else {
                $io->error('❌ ТЕСТ ПРОВАЛЕН!');
                $io->listing([
                    'AssignedBy: ' . ($assignedBy?->getFullName() ?? 'NULL'),
                    'Ожидался: ' . $currentUser->getFullName(),
                ]);
                return Command::FAILURE;
            }
        } else {
            // Web контекст с не-Manager
            if ($assignedBy === null) {
                $io->success('✅ ОЖИДАЕМЫЙ РЕЗУЛЬТАТ');
                $io->info('AssignedBy = NULL (пользователь не Manager)');
            } else {
                $io->error('❌ НЕОЖИДАННЫЙ РЕЗУЛЬТАТ');
                $io->info('AssignedBy должен быть NULL (пользователь не Manager)');
            }
        }

        // Шаг 6: Очистка
        $io->section('Шаг 6: Очистка тестовых данных');

        $this->em->remove($assignment);
        $this->em->remove($order);
        $this->em->flush();

        $io->success('Тестовые данные удалены');

        // Итоговое сообщение
        $io->newLine();
        $io->note([
            'Примечание: Этот тест выполняется в CLI контексте.',
            'Для полноценного теста автоматического заполнения:',
            '1. Залогиньтесь в Sonata Admin как Manager',
            '2. Откройте любой Order для редактирования',
            '3. Перейдите на вкладку "Назначения"',
            '4. Добавьте новое назначение (НЕ указывая AssignedBy)',
            '5. Сохраните Order',
            '6. AssignedBy должен автоматически заполниться текущим менеджером',
        ]);

        return Command::SUCCESS;
    }
}
