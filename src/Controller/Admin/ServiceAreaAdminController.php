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

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MatrixItem;
use App\Entity\ServiceArea;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceAreaAdminController extends CRUDController
{
    /**
     * Duplicates a ServiceArea (settings + GeoArea links + MatrixItems) so a similar
     * tariff can be quickly re-used for another carrier.
     *
     * The clone keeps name+currency+country+carrier and reuses GeoArea references
     * (many-to-many) as-is, but resets isHomeZone to false to avoid clashing with the
     * unique (carrier, country, home_zone) constraint.
     */
    public function copyAction(Request $request, EntityManagerInterface $entityManager): Response
    {
        $object = $this->assertObjectExists($request, true);
        \assert($object instanceof ServiceArea);

        $this->admin->checkAccess('show', $object);
        $this->admin->checkAccess('create');

        $copy = (new ServiceArea())
            ->setName($this->buildCopyName((string) $object->getName()))
            ->setCurrency((string) $object->getCurrency())
            ->setCountry($object->getCountry())
            ->setCarrier($object->getCarrier())
            ->setIsHomeZone(false);

        foreach ($object->getGeoAreas() as $geoArea) {
            $copy->addGeoArea($geoArea);
        }

        foreach ($object->getMatrixItems() as $sourceItem) {
            $itemCopy = (new MatrixItem())
                ->setPrice((int) $sourceItem->getPrice())
                ->setWeightFrom((int) $sourceItem->getWeightFrom())
                ->setWeightTo((int) $sourceItem->getWeightTo());
            $copy->addMatrixItem($itemCopy);
            $entityManager->persist($itemCopy);
        }

        $entityManager->persist($copy);
        $entityManager->flush();

        $this->addFlash(
            'sonata_flash_success',
            $this->trans('flash.service_area_copied', [], 'AppBundle'),
        );

        return new RedirectResponse($this->admin->generateObjectUrl('edit', $copy));
    }

    private function buildCopyName(string $name): string
    {
        $suffix = $this->trans('service_area.copy_name_suffix', [], 'AppBundle');
        $base = preg_match('/\s*\([^)]*\)\s*$/', $name) === 1
            ? preg_replace('/\s*\([^)]*\)\s*$/', '', $name)
            : $name;

        return trim((string) $base).' '.$suffix;
    }
}
