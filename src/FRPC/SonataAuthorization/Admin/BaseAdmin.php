<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2022 SIA SLYFOX.
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

namespace FRPC\SonataAuthorization\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;

class BaseAdmin extends AbstractAdmin
{
    public const BASE_LIST_TIME_HH_II__FORMAT = 'H:i';
    public const BASE_LIST_DATETIME_FORMAT = 'H:i:s / d.m.Y';
    public const BASE_SHOW_DATE_FORMAT = 'd.m.Y';
    public const BASE_SHOW_DATETIME_FORMAT = 'H:i:s / d.m.Y';

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues[DatagridInterface::SORT_BY] = 'createdAt';
        $sortValues[DatagridInterface::SORT_ORDER] = 'desc';

        parent::configureDefaultSortValues($sortValues);
    }

    protected function configureListFields(ListMapper $list): void
    {
        if ($this->hasSameRole()) {
            $list->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'show' => [],
                    'delete' => [],
                ],
            ]);
        }
    }

    protected function hasSameRole(): bool
    {
        return $this->isGranted('EDIT') || $this->isGranted('VIEW') || $this->isGranted('DELETE');
    }
}