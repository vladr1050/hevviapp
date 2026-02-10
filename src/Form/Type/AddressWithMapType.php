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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * AddressWithMapType
 * 
 * Custom Form Type для поля адреса с интеграцией интерактивной карты.
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает только за рендеринг поля с картой
 * - Open/Closed: расширяемый через опции без изменения кода
 * - Liskov Substitution: может заменить TextType
 * - Interface Segregation: использует только необходимые интерфейсы Symfony Forms
 * - Dependency Inversion: зависит от абстракций (AbstractType, interfaces)
 */
class AddressWithMapType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        
        // Передаем параметры в view для рендеринга
        $view->vars['enable_map'] = $options['enable_map'];
        $view->vars['map_button_label'] = $options['map_button_label'];
        $view->vars['map_button_icon'] = $options['map_button_icon'];
        $view->vars['modal_id'] = $options['modal_id'] ?? $this->generateModalId($view);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'enable_map' => true,
            'map_button_label' => 'Выбрать на карте',
            'map_button_icon' => 'fas fa-map-marked-alt',
            'modal_id' => null, // Автоматически генерируется если не указан
        ]);

        $resolver->setAllowedTypes('enable_map', 'bool');
        $resolver->setAllowedTypes('map_button_label', 'string');
        $resolver->setAllowedTypes('map_button_icon', 'string');
        $resolver->setAllowedTypes('modal_id', ['null', 'string']);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): string
    {
        return TextType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'address_with_map';
    }

    /**
     * Генерация уникального ID для модального окна
     * 
     * @param FormView $view
     * @return string
     */
    private function generateModalId(FormView $view): string
    {
        return 'address_map_modal_' . $view->vars['id'];
    }
}
