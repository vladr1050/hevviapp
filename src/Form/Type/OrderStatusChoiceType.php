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

namespace App\Form\Type;

use App\Entity\Order;
use App\Service\OrderStatusCounterService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Кастомный тип формы для выбора статуса заказа с отображением счетчиков.
 * Следует принципу Open/Closed - расширяет ChoiceType, не модифицируя его.
 */
class OrderStatusChoiceType extends AbstractType
{
    public function __construct(
        private readonly OrderStatusCounterService $counterService
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => Order::STATUS,
            'choice_translation_domain' => 'AppBundle',
            'show_counts' => true, // Опция для включения/выключения счетчиков
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($options['show_counts']) {
            $statusCounts = $this->counterService->getStatusCounts();
            
            // Добавляем счетчики к метке каждого выбора
            $choices = [];
            foreach ($view->vars['choices'] as $choice) {
                $statusValue = $choice->value;
                $count = $statusCounts[$statusValue] ?? 0;
                $label = $choice->label;
                
                // Создаем новую метку с счетчиком
                $choice->label = $count > 0 ? sprintf('%s (%d)', $label, $count) : $label;
                $choices[] = $choice;
            }
            
            $view->vars['choices'] = $choices;
        }
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'order_status_choice';
    }
}
