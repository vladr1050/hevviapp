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
use App\Repository\ServiceAreaRepository;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
            ->add('carrier')
            ->add('country')
            ->add('isHomeZone', null, [
                'label' => 'list.label_is_home_zone',
            ])
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
            ->add('carrier')
            ->add('country')
            ->add('isHomeZone', null, [
                'label' => 'show.label_is_home_zone',
            ])
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
            ->add('carrier', ModelAutocompleteType::class, [
                'required' => false,
                'property' => ['legalName', 'email', 'registrationNumber'],
                'to_string_callback' => static fn ($entity): string => (string) $entity->getLegalName(),
                'callback' => static function ($admin, $property, $value): void {
                    $datagrid = $admin->getDatagrid();
                    $queryBuilder = $datagrid->getQuery();
                    $rootAlias = current($queryBuilder->getRootAliases());
                    $queryBuilder
                        ->andWhere(
                            $queryBuilder->expr()->orX(
                                $queryBuilder->expr()->like('LOWER('.$rootAlias.'.legalName)', ':search'),
                                $queryBuilder->expr()->like('LOWER('.$rootAlias.'.email)', ':search'),
                                $queryBuilder->expr()->like('LOWER('.$rootAlias.'.registrationNumber)', ':search'),
                            )
                        )
                        ->setParameter('search', '%'.mb_strtolower($value).'%');
                },
                'label' => 'form.label_service_area_carrier',
                'help' => 'form.help_service_area_carrier',
            ])
            ->add('country', ChoiceType::class, [
                'choices' => [
                    'Latvia (LV)' => 'LV',
                ],
                'required' => true,
                'label' => 'form.label_service_area_country',
            ])
            ->add('isHomeZone', CheckboxType::class, [
                'required' => false,
                'label' => 'form.label_is_home_zone',
                'help' => 'form.help_is_home_zone',
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

    protected function prePersist(object $object): void
    {
        if ($object instanceof ServiceArea) {
            $this->ensureSingleHomeZone($object);
        }
    }

    protected function preUpdate(object $object): void
    {
        if ($object instanceof ServiceArea) {
            $this->ensureSingleHomeZone($object);
        }
    }

    private function ensureSingleHomeZone(ServiceArea $area): void
    {
        if (!$area->isHomeZone() || $area->getCarrier() === null) {
            return;
        }
        $em = $this->getModelManager()->getEntityManager(ServiceArea::class);
        $repo = $em->getRepository(ServiceArea::class);
        if ($repo instanceof ServiceAreaRepository) {
            $repo->demoteOtherHomeZones($area->getCarrier(), $area->getCountry(), $area->getId());
        }
    }
}
