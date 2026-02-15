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

namespace App\Controller\Admin;

use Sonata\AdminBundle\Controller\CRUDController;

/**
 * ServiceAreaAdminController
 *
 * Контроллер для управления ServiceArea в Sonata Admin.
 * 
 * ПРИМЕЧАНИЕ: Кастомные зоны создаются напрямую в БД через API,
 * поэтому не требуется дополнительная обработка в контроллере.
 * Используется стандартная функциональность CRUDController.
 */
class ServiceAreaAdminController extends CRUDController
{
    // Стандартный CRUDController - кастомные зоны уже в БД
}
