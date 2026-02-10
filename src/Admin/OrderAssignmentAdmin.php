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

use App\Entity\OrderAssignment;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OrderAssignmentAdmin extends BaseAdmin
{
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('carrier', null, [
                'associated_property' => 'legalName',
            ])
            ->add('status', TextType::class, [
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    return ucfirst(strtolower(array_flip(OrderAssignment::STATUS)[$object->getStatus()]));
                },
            ])
            ->add('assignedBy', null, [
                'label' => 'list.label_assigned_by',
                'associated_property' => function ($manager) {
                    return sprintf('%s %s', $manager->getFirstName(), $manager->getLastName());
                },
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('carrier', null, [
                'associated_property' => function ($carrier) {
                    return sprintf('%s (%s)', $carrier->getLegalName(), $carrier->getEmail());
                },
            ])
            ->add('status', TextType::class, [
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    return ucfirst(strtolower(array_flip(OrderAssignment::STATUS)[$object->getStatus()]));
                },
            ])
            ->add('assignedBy', null, [
                'label' => 'show.label_assigned_by',
                'associated_property' => function ($manager) {
                    return sprintf('%s %s (%s)', $manager->getFirstName(), $manager->getLastName(), $manager->getEmail());
                },
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('carrier', ModelAutocompleteType::class, [
                'required' => true,
                'property' => ['email', 'phone', 'legalName'],
                'to_string_callback' => function ($entity) {
                    return $entity->getLegalName();
                },
                'callback' => function ($admin, $property, $value) {
                    $datagrid = $admin->getDatagrid();
                    $queryBuilder = $datagrid->getQuery();
                    $queryBuilder
                        ->andWhere(
                            $queryBuilder->expr()->orX(
                                $queryBuilder->expr()->like('LOWER(o.email)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.phone)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.legalName)', ':search')
                            )
                        )
                        ->setParameter('search', '%' . mb_strtolower($value) . '%');
                },
            ])
            ->add('status', ChoiceType::class, [
                'choices' => OrderAssignment::STATUS,
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
            ])
            ->add('assignedBy', ModelAutocompleteType::class, [
                'required' => false,
                'label' => 'form.label_assigned_by',
                'help' => 'form.label_help_assigned_by',
                'property' => ['firstName', 'lastName', 'email'],
                'to_string_callback' => function ($entity) {
                    return sprintf('%s %s', $entity->getFirstName(), $entity->getLastName());
                },
                'callback' => function ($admin, $property, $value) {
                    $datagrid = $admin->getDatagrid();
                    $queryBuilder = $datagrid->getQuery();
                    $queryBuilder
                        ->andWhere(
                            $queryBuilder->expr()->orX(
                                $queryBuilder->expr()->like('LOWER(o.email)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.firstName)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.lastName)', ':search'),
                                $queryBuilder->expr()->like(
                                    "LOWER(CONCAT(o.firstName, ' ', o.lastName))",
                                    ':search'
                                )
                            )
                        )
                        ->setParameter('search', '%' . mb_strtolower($value) . '%');
                },
            ]);
    }
}
