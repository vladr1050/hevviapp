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
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OrderOfferAdmin extends BaseAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $subject = $this->getSubject();
        $isLocked = $subject instanceof OrderOffer && $subject->isActiveDraftForOfferedOrder();

        $amountOptions = [
            'required' => true,
        ];
        if ($isLocked) {
            $amountOptions['disabled'] = true;
            $amountOptions['help'] = 'order_offer.adjust.help.use_adjust_form';
        }

        $form
            ->add('netto', IntegerType::class, array_merge($amountOptions, [
                'label' => 'form.label_netto',
                'help' => $amountOptions['help'] ?? 'order_offer.help.netto_info',
            ]))
            ->add('fee', IntegerType::class, array_merge($amountOptions, [
                'label' => 'form.label_fee',
                'required' => false,
                'help' => $amountOptions['help'] ?? 'order_offer.help.fee_info',
            ]))
            ->add('vat', IntegerType::class, array_merge($amountOptions, [
                'label' => 'form.label_vat',
                'help' => $amountOptions['help'] ?? 'order_offer.help.vat_info',
            ]))
            ->add('brutto', IntegerType::class, array_merge($amountOptions, [
                'label' => 'form.label_brutto',
                'help' => $amountOptions['help'] ?? 'order_offer.help.brutto_info',
            ]))
            ->add('status', ChoiceType::class, [
                'label' => 'form.label_offer_status',
                'choices' => OrderOffer::STATUS,
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
                'disabled' => $isLocked,
            ]);
    }

    /**
     * @param OrderOffer $object
     */
    protected function preUpdate(object $object): void
    {
        if (!$object->isActiveDraftForOfferedOrder()) {
            return;
        }

        $em = $this->getModelManager()->getEntityManager(OrderOffer::class);
        $original = $em->getUnitOfWork()->getOriginalEntityData($object);

        foreach (['netto', 'fee', 'vat', 'brutto', 'status'] as $field) {
            $getter = 'get'.ucfirst($field);
            if (($object->$getter() ?? null) !== ($original[$field] ?? null)) {
                throw new ModelManagerException($this->trans(
                    'order_offer.adjust.error.inline_edit_blocked',
                    [],
                    'AppBundle',
                ));
            }
        }
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('_sender_price_breakdown', null, [
                'label'    => 'show.label_sender_delivery_price',
                'template' => 'admin/CRUD/show_sender_order_price_breakdown.html.twig',
                'virtual_field' => true,
            ])
            ->add('status', 'choice', [
                'label' => 'show.label_offer_status',
                'catalogue' => 'AppBundle',
                'choices' => array_flip(OrderOffer::STATUS),
            ])
            ->add('pricingSource', TextType::class, [
                'label' => 'show.label_offer_pricing_source',
                'accessor' => static fn (OrderOffer $offer): string => match ($offer->getPricingSource()) {
                    \App\Enum\OfferPricingSource::MANUAL => 'order_offer.pricing_source.manual',
                    \App\Enum\OfferPricingSource::CALCULATED => 'order_offer.pricing_source.calculated',
                },
                'catalogue' => 'AppBundle',
            ])
            ->add('adjustmentReason', null, [
                'label' => 'show.label_offer_adjustment_reason',
            ])
            ->add('adjustedByManager', null, [
                'label' => 'show.label_offer_adjusted_by',
                'associated_property' => static fn ($manager) => $manager
                    ? sprintf('%s %s', $manager->getFirstName(), $manager->getLastName())
                    : '—',
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }
}
