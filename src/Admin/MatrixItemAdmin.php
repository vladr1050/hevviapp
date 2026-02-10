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

use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class MatrixItemAdmin extends BaseAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('weightFrom')
            ->add('weightTo')
            ->add('price');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('weightFrom')
            ->add('weightTo')
            ->add('price')
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('weightFrom')
            ->add('weightTo')
            ->add('price')
            ->add('serviceArea')
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('weightFrom', IntegerType::class, [
                'required' => true,
            ])
            ->add('weightTo', IntegerType::class, [
                'required' => true,
            ])
            ->add('price', IntegerType::class, [
                'required' => true,
            ]);
    }
}
