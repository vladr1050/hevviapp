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
use App\Entity\User;
use Doctrine\DBAL\Types\TextType;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use FRPC\SonataAuthorization\Form\Type\PlainPasswordType;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserAdmin extends BaseAdmin
{
    protected function configureListFields(ListMapper $list): void
    {
        $list
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
            ->add('vatNumber', null, [
                'label' => 'list.label_vat_number',
            ])
            ->add('state', TextType::class, [
                'sortable' => false,
                'mapped' => false,
                'template' => 'admin/fields/list/security/state_flags.html.twig',
                'catalogue' => 'AppBundle',
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('firstName')
            ->add('lastName')
            ->add('email')
            ->add('phone')
            ->add('companyName')
            ->add('companyRegistrationNumber')
            ->add('companyAddress')
            ->add('vatNumber')
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
            ->end()
            ->with('contacts', [
                'class' => 'col-md-6',
            ])
            ->add('phone')
            ->add('email')
            ->end()
            ->with('legal', [
                'class' => 'col-md-12',
            ])
            ->add('companyName')
            ->add('companyRegistrationNumber')
            ->add('companyAddress')
            ->add('vatNumber', null, [
                'required' => false,
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
        $this->ensureSenderRole($object);
    }

    protected function preUpdate(object $object): void
    {
        $this->ensureSenderRole($object);
    }

    /**
     * Public pages under /user/ require ROLE_USER (security access_control). If roles were
     * cleared in DB or by a future form field, restore the baseline.
     */
    private function ensureSenderRole(object $object): void
    {
        if (!$object instanceof User) {
            return;
        }
        $roles = $object->getRoles();
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
            $object->setRoles(array_values(array_unique($roles)));
        }
    }
}
