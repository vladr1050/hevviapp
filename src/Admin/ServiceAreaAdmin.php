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

use App\Entity\ServiceArea;
use App\Form\Type\GeoAreaSelectionType;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ServiceAreaAdmin extends BaseAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('name');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name')
            ->add('currency')
            ->add('geoAreas', null, [
                'label' => 'list.label_geo_areas_count',
                'template' => 'admin/CRUD/list_collection_count.html.twig',
            ])
            ->add('matrixItems', null, [
                'label' => 'list.label_matrix_items_count',
                'template' => 'admin/CRUD/list_collection_count.html.twig',
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
            ->add('name')
            ->add('currency')
            ->add('geoAreas', null, [
                'template' => 'admin/fields/geo_areas_show.html.twig',
            ])
            ->add('matrixItems')
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('tabs_general')
            ->with('service_area_info', [
                'class' => 'col-md-12',
            ])
            ->add('name', TextType::class, [
                'required' => true,
            ])
            ->add('currency', ChoiceType::class, [
                'choices' => ServiceArea::CURRENCY,
                'required' => true,
            ])
            ->end()
            ->end()
            ->tab('tabs_geo_areas')
            ->with('geo_areas_selection', [
                'class' => 'col-md-12',
            ])
            ->add('geoAreas', GeoAreaSelectionType::class, [
                'label' => false,
                'enable_map' => true,
                'show_country_filter' => true,
            ])
            ->end()
            ->end()
            ->tab('tabs_matrix_items')
            ->with('matrix_items_list', [
                'class' => 'col-md-12',
            ])
            ->add('matrixItems', CollectionType::class, [
                'by_reference' => false,
                'type' => AdminType::class,
                'label' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ])
            ->end()
            ->end();
    }
}
