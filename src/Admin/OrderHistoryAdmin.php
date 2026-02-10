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

namespace App\Admin;

use App\Entity\Order;
use App\Entity\OrderHistory;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OrderHistoryAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        // Убираем все маршруты редактирования - только просмотр
        $collection
            ->remove('create')
            ->remove('edit')
            ->remove('delete')
            ->remove('export');
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('relatedOrder', ModelFilter::class, [
                'label' => 'filter.label_order',
            ])
            ->add('status', ChoiceFilter::class, [
                'label' => 'filter.label_status',
                'field_type' => \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class,
                'field_options' => [
                    'choices' => Order::STATUS,
                    'choice_translation_domain' => 'AppBundle',
                ],
            ])
            ->add('changedBy', ChoiceFilter::class, [
                'label' => 'filter.label_changed_by',
                'field_type' => \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class,
                'field_options' => [
                    'choices' => OrderHistory::CHANGED_BY,
                    'choice_translation_domain' => 'AppBundle',
                ],
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('relatedOrder', null, [
                'label' => 'list.label_order',
            ])
            ->add('status', TextType::class, [
                'label' => 'list.label_status',
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    $statusKey = array_flip(Order::STATUS)[$object->getStatus()] ?? 'UNKNOWN';
                    return ucfirst(strtolower($statusKey));
                },
            ])
            ->add('changedBy', TextType::class, [
                'label' => 'list.label_changed_by',
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    $changedByKey = array_flip(OrderHistory::CHANGED_BY)[$object->getChangedBy()] ?? 'UNKNOWN';
                    return ucfirst(strtolower($changedByKey));
                },
            ])
            ->add('createdAt', 'datetime', [
                'label' => 'list.label_changed_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);

        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('relatedOrder', null, [
                'label' => 'show.label_order',
            ])
            ->add('status', TextType::class, [
                'label' => 'show.label_status',
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    $statusKey = array_flip(Order::STATUS)[$object->getStatus()] ?? 'UNKNOWN';
                    return ucfirst(strtolower($statusKey));
                },
            ])
            ->add('changedBy', TextType::class, [
                'label' => 'show.label_changed_by',
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    $changedByKey = array_flip(OrderHistory::CHANGED_BY)[$object->getChangedBy()] ?? 'UNKNOWN';
                    return ucfirst(strtolower($changedByKey));
                },
            ])
            ->add('meta', null, [
                'label' => 'show.label_meta',
            ])
            ->add('createdAt', null, [
                'label' => 'show.label_created_at',
            ])
            ->add('updatedAt', null, [
                'label' => 'show.label_updated_at',
            ]);
    }
}
