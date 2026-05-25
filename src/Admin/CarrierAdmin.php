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

use App\Entity\BaseDBO;
use App\Entity\Carrier;
use App\Enum\PricingAlgorithm;
use App\Repository\CarrierRepository;
use Doctrine\DBAL\Types\TextType;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use FRPC\SonataAuthorization\Form\Type\PlainPasswordType;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class CarrierAdmin extends BaseAdmin
{
    use SonataPlainPasswordAdminTrait;

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues[DatagridInterface::SORT_BY] = 'createdAt';
        $sortValues[DatagridInterface::SORT_ORDER] = 'desc';
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('legalName')
            ->add('fullname', TextType::class, [
                'sortable' => true,
                'sort_field_mapping' => [
                    'fieldName' => 'firstName',
                ],
                'accessor' => static function ($object) {
                    return sprintf('%s %s', $object->getFirstName(), $object->getLastName());
                },
            ])
            ->add('email')
            ->add('phone')
            ->add('registrationNumber')
            ->add('isDefaultForPricing', null, [
                'label' => 'list.label_default_for_pricing',
            ])
            ->add('pricingAlgorithm', null, [
                'label' => 'list.label_pricing_algorithm',
                'accessor' => static fn (Carrier $c): string => $c->getPricingAlgorithm()->value,
            ])
            ->add('vatNumber', null, [
                'label' => 'list.label_vat_number',
            ])
            ->add('state', TextType::class, [
                'sortable' => false,
                'mapped' => false,
                'template' => 'admin/fields/list/security/state_flags.html.twig',
                'catalogue' => 'AppBundle',
            ]);

        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('firstName')
            ->add('lastName')
            ->add('legalName')
            ->add('email')
            ->add('phone')
            ->add('address')
            ->add('registrationNumber')
            ->add('vatNumber')
            ->add('vatRate', null, [
                'label' => 'form.label_carrier_vat_rate_percent',
            ])
            ->add('isDefaultForPricing')
            ->add('pricingAlgorithm', 'choice', [
                'label' => 'form.label_pricing_algorithm',
                'catalogue' => 'AppBundle',
                'choices' => array_flip(array_map(
                    static fn (PricingAlgorithm $a): string => $a->value,
                    PricingAlgorithm::cases(),
                )),
                'choice_translation_domain' => 'AppBundle',
                'choice_label' => static fn (string $value): string => PricingAlgorithm::from($value)->labelKey(),
            ])
            ->add('priceCoefficient')
            ->add('iban')
            ->add('bankAccountHolder')
            ->add('locale')
            ->add('state', TextType::class, [
                'sortable' => false,
                'mapped' => false,
                'template' => 'admin/fields/show/security/state_flags.html.twig',
                'catalogue' => 'AppBundle',
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('tabs_general')
            ->with('profile', [
                'class' => 'col-md-6',
            ])
            ->add('firstName')
            ->add('lastName')
            ->add('legalName')
            ->end()
            ->with('contacts', [
                'class' => 'col-md-6',
            ])
            ->add('phone')
            ->add('email')
            ->add('address')
            ->add('registrationNumber')
            ->add('vatNumber', null, [
                'required' => false,
            ])
            ->add('vatRate', null, [
                'required' => false,
                'label' => 'form.label_carrier_vat_rate_percent',
                'help' => 'form.help_carrier_vat_rate_percent',
            ])
            ->end()
            ->with('payment', [
                'class' => 'col-md-12',
            ])
            ->add('iban', null, [
                'required' => false,
                'attr' => [
                    'maxlength' => 34,
                ],
            ])
            ->add('bankAccountHolder', null, [
                'required' => false,
            ])
            ->end()
            ->with('pricing', [
                'class' => 'col-md-12',
                'label' => 'form.group_carrier_pricing',
            ])
            ->add('isDefaultForPricing', CheckboxType::class, [
                'required' => false,
                'label' => 'form.label_default_for_pricing',
                'help' => 'form.help_default_for_pricing',
            ])
            ->add('pricingAlgorithm', ChoiceType::class, [
                'choices' => PricingAlgorithm::choices(),
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
                'label' => 'form.label_pricing_algorithm',
            ])
            ->add('priceCoefficient', NumberType::class, [
                'required' => false,
                'scale' => 4,
                'html5' => true,
                'label' => 'form.label_price_coefficient',
                'help' => 'form.help_price_coefficient',
            ])
            ->end()
            ->with('general', [
                'class' => 'col-md-12',
            ])
            ->add('locale', ChoiceType::class, [
                'choices' => BaseDBO::BASE_LOCALE,
                'required' => true,
            ])
            ->add('plainPassword', PlainPasswordType::class, [
                'required' => false,
                'help' => 'form.label_help_plain_password',
                'always_empty' => true,
                'data' => null,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'maxlength' => 255,
                ],
            ])
            ->add('state', ChoiceType::class, [
                'choices' => BaseDBO::BASE_STATE,
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
                'data' => $this->getStateChoicesFromInt($this->getSubject()?->getState()),
            ]);
    }

    private function getStateChoicesFromInt(int $state): ?string
    {
        return array_find(BaseDBO::BASE_STATE, static fn($bit) => ($state & $bit) === $bit);
    }

    protected function prePersist(object $object): void
    {
        $this->ensureCarrierRole($object);
        if ($object instanceof Carrier) {
            $this->ensureSingleDefaultForPricing($object);
        }
    }

    protected function preUpdate(object $object): void
    {
        $this->ensureCarrierRole($object);
        $this->applyPlainPasswordFromAdminForm($object);
        if ($object instanceof Carrier) {
            $this->ensureSingleDefaultForPricing($object);
        }
    }

    private function ensureSingleDefaultForPricing(Carrier $carrier): void
    {
        if (!$carrier->isDefaultForPricing()) {
            return;
        }
        $em = $this->getModelManager()->getEntityManager(Carrier::class);
        $repo = $em->getRepository(Carrier::class);
        if ($repo instanceof CarrierRepository) {
            $repo->demoteOtherDefaultForPricing($carrier->getId());
        }
    }

    /** /carrier/* requires ROLE_CARRIER in the JWT. */
    private function ensureCarrierRole(object $object): void
    {
        if (!$object instanceof Carrier) {
            return;
        }
        $roles = $object->getRoles();
        if (!in_array('ROLE_CARRIER', $roles, true)) {
            $roles[] = 'ROLE_CARRIER';
            $object->setRoles(array_values(array_unique($roles)));
        }
    }
}
