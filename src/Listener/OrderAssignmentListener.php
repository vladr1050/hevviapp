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

namespace App\Listener;

use App\Entity\Manager;
use App\Entity\OrderAssignment;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * EntityListener для автоматического заполнения поля AssignedBy в OrderAssignment
 * 
 * При создании нового назначения автоматически устанавливает текущего менеджера
 * в поле AssignedBy, если пользователь залогинен и является менеджером.
 * 
 * Принципы:
 * - Single Responsibility: Отвечает только за установку AssignedBy
 * - Dependency Inversion: Зависит от абстракций (Security, LoggerInterface)
 */
class OrderAssignmentListener
{
    public function __construct(
        private readonly Security $security,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Вызывается перед сохранением новой сущности OrderAssignment в БД
     * 
     * @param OrderAssignment $assignment
     * @return void
     */
    public function prePersist(OrderAssignment $assignment): void
    {
        // Если AssignedBy уже установлен, не перезаписываем
        if ($assignment->getAssignedBy() !== null) {
            $this->logger->info('OrderAssignmentListener: AssignedBy уже установлен, пропускаем', [
                'assignment_id' => 'new',
                'assigned_by' => $assignment->getAssignedBy()->getFullName(),
            ]);
            return;
        }

        // Получаем текущего пользователя
        $user = $this->security->getUser();

        if ($user === null) {
            $this->logger->warning('OrderAssignmentListener: Пользователь не залогинен', [
                'assignment_id' => 'new',
            ]);
            return;
        }

        // Проверяем, что пользователь является менеджером
        if (!$user instanceof Manager) {
            $this->logger->warning('OrderAssignmentListener: Текущий пользователь не является менеджером', [
                'assignment_id' => 'new',
                'user_class' => get_class($user),
            ]);
            return;
        }

        // Устанавливаем текущего менеджера в AssignedBy
        $assignment->setAssignedBy($user);

        $this->logger->info('OrderAssignmentListener: AssignedBy установлен автоматически', [
            'assignment_id' => 'new',
            'manager_id' => $user->getId()?->toRfc4122(),
            'manager_name' => $user->getFullName(),
        ]);
    }
}
