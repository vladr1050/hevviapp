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

use App\Entity\GeoArea;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GeoAreaSelectionType
 * 
 * Custom Form Type для выбора гео-зон с интерактивной картой.
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает только за рендеринг поля выбора гео-зон
 * - Open/Closed: расширяемый через опции без изменения кода
 * - Liskov Substitution: может заменить EntityType
 * - Interface Segregation: использует только необходимые интерфейсы Symfony Forms
 * - Dependency Inversion: зависит от абстракций (AbstractType, interfaces)
 */
class GeoAreaSelectionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        
        // Передаем параметры в view для рендеринга
        $view->vars['enable_map'] = $options['enable_map'];
        $view->vars['show_country_filter'] = $options['show_country_filter'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => GeoArea::class,
            'choice_label' => 'name',
            'multiple' => true,
            'required' => false,
            'enable_map' => true,
            'show_country_filter' => true,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('g')
                    ->orderBy('g.name', 'ASC');
            },
            'attr' => [
                'class' => 'geo-area-selection-field',
            ],
        ]);

        $resolver->setAllowedTypes('enable_map', 'bool');
        $resolver->setAllowedTypes('show_country_filter', 'bool');
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): string
    {
        return EntityType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'geo_area_selection';
    }
}
