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

use App\Entity\Cargo;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CargoAdmin extends BaseAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('type', null, [
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => Cargo::TYPE,
                    'choice_translation_domain' => 'AppBundle',
                ],
            ])
            ->add('weightKg');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
            ->add('type', TextType::class, [
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    return ucfirst(strtolower(array_flip(Cargo::TYPE)[$object->getType()]));
                },
            ])
            ->add('quantity')
            ->add('weightKg', null, [
                'label' => 'list.label_weight_kg',
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('name')
            ->add('type', TextType::class, [
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    return ucfirst(strtolower(array_flip(Cargo::TYPE)[$object->getType()]));
                },
            ])
            ->add('quantity')
            ->add('weightKg')
            ->add('dimensionsCm')
            ->add('comment')
            ->add('relatedOrder')
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('cargo_info', [
                'class' => 'col-md-6',
            ])
            ->add('name', TextType::class)
            ->add('type', ChoiceType::class, [
                'choices' => Cargo::TYPE,
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
            ])
            ->add('quantity', IntegerType::class, [
                'required' => true,
            ])
            ->add('weightKg', IntegerType::class, [
                'required' => true,
                'label' => 'form.label_weight_kg',
            ])
            ->add('dimensionsCm', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => '120,80,100',
                ],
                'help' => 'form.help_dimensions_cm',
            ])
            ->end()
            ->with('cargo_properties', [
                'class' => 'col-md-6',
            ])
            ->add('comment', TextareaType::class, [
                'required' => false,
            ])
            ->end();
    }
}
