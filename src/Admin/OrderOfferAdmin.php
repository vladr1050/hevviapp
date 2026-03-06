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

use App\Entity\OrderOffer;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class OrderOfferAdmin extends BaseAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('netto', IntegerType::class, [
                'label' => 'form.label_netto',
                'required' => true,
                'help' => 'order_offer.help.netto_info',
            ])
            ->add('fee', IntegerType::class, [
                'label' => 'form.label_fee',
                'required' => false,
                'help' => 'order_offer.help.fee_info',
            ])
            ->add('vat', IntegerType::class, [
                'label' => 'form.label_vat',
                'required' => true,
                'help' => 'order_offer.help.vat_info',
            ])
            ->add('brutto', IntegerType::class, [
                'label' => 'form.label_brutto',
                'required' => true,
                'help' => 'order_offer.help.brutto_info',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'form.label_offer_status',
                'choices' => OrderOffer::STATUS,
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
            ]);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('netto', null, [
                'label' => 'show.label_netto',
            ])
            ->add('fee', null, [
                'label' => 'show.label_fee',
            ])
            ->add('vat', null, [
                'label' => 'show.label_vat',
            ])
            ->add('brutto', null, [
                'label' => 'show.label_brutto',
            ])
            ->add('status', 'choice', [
                'label' => 'show.label_offer_status',
                'catalogue' => 'AppBundle',
                'choices' => array_flip(OrderOffer::STATUS),
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }
}
