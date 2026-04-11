<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Admin;

use App\Entity\AppSettings;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AppSettingsAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        parent::configureRoutes($collection);
        $collection->remove('delete');
        $collection->remove('export');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('restrictGeographicSearch', null, ['label' => 'show.label_appsettings_restrict'])
            ->add('nominatimCountryCodes', null, ['label' => 'show.label_appsettings_country_codes'])
            ->add('bboxMinLatitude', null, ['label' => 'show.label_appsettings_bbox_min_lat'])
            ->add('bboxMaxLatitude', null, ['label' => 'show.label_appsettings_bbox_max_lat'])
            ->add('bboxMinLongitude', null, ['label' => 'show.label_appsettings_bbox_min_lon'])
            ->add('bboxMaxLongitude', null, ['label' => 'show.label_appsettings_bbox_max_lon'])
            ->add('defaultMapLatitude', null, ['label' => 'show.label_appsettings_default_lat'])
            ->add('defaultMapLongitude', null, ['label' => 'show.label_appsettings_default_lng'])
            ->add('defaultMapZoom', null, ['label' => 'show.label_appsettings_default_zoom'])
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('appsettings_geocoding', ['class' => 'col-md-12', 'label' => 'form.group_appsettings_geocoding'])
            ->add('restrictGeographicSearch', CheckboxType::class, [
                'required' => false,
                'label' => 'form.label_appsettings_restrict',
                'help' => 'form.help_appsettings_restrict',
            ])
            ->add('nominatimCountryCodes', TextType::class, [
                'required' => false,
                'label' => 'form.label_appsettings_country_codes',
                'help' => 'form.help_appsettings_country_codes',
            ])
            ->end()
            ->with('appsettings_bbox', ['class' => 'col-md-12', 'label' => 'form.group_appsettings_bbox'])
            ->add('bboxMinLatitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'label' => 'form.label_appsettings_bbox_min_lat',
                'help' => 'form.help_appsettings_bbox',
            ])
            ->add('bboxMaxLatitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'label' => 'form.label_appsettings_bbox_max_lat',
            ])
            ->add('bboxMinLongitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'label' => 'form.label_appsettings_bbox_min_lon',
            ])
            ->add('bboxMaxLongitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'label' => 'form.label_appsettings_bbox_max_lon',
            ])
            ->end()
            ->with('appsettings_map', ['class' => 'col-md-12', 'label' => 'form.group_appsettings_map'])
            ->add('defaultMapLatitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'label' => 'form.label_appsettings_default_lat',
                'help' => 'form.help_appsettings_map_defaults',
            ])
            ->add('defaultMapLongitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
                'html5' => true,
                'label' => 'form.label_appsettings_default_lng',
            ])
            ->add('defaultMapZoom', IntegerType::class, [
                'required' => false,
                'label' => 'form.label_appsettings_default_zoom',
            ])
            ->end();
    }

    protected function prePersist(object $object): void
    {
        $this->assertSingleton($object);
    }

    private function assertSingleton(object $object): void
    {
        if (!$object instanceof AppSettings) {
            return;
        }
        $em = $this->getModelManager()->getEntityManager(AppSettings::class);
        $count = $em->getRepository(AppSettings::class)->count([]);
        if ($count > 0) {
            throw new \RuntimeException('Only one AppSettings record is allowed. Edit the existing row.');
        }
    }
}
