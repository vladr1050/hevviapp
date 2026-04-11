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

use App\Entity\Manager;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use FRPC\SonataAuthorization\Form\Type\PlainPasswordType;
use FRPC\SonataAuthorization\Form\Type\RolesMatrixType;

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
class ManagerAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('export');
    }
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('username')
            ->add('lastLogin', null, [
                'template' => '@SonataAuthorization/Admin/CRUD/list_time_ago.html.twig',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
                'full' => true,
                'show_datetime' => false,
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ])
            ->add('updatedAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);

        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->tab('tabs_general')
            ->with('general', [
                'class' => 'col-md-12',
            ])
            ->add('email')
            ->add('phoneNumber')
            ->add('lastLogin', null, [
                'template' => '@SonataAuthorization/Admin/CRUD/show_time_ago.html.twig',
                'format' => self::BASE_SHOW_DATETIME_FORMAT,
                'full' => true,
                'show_datetime' => true,
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_SHOW_DATE_FORMAT,
            ])
            ->add('updatedAt', 'datetime', [
                'format' => self::BASE_SHOW_DATE_FORMAT,
            ])
            ->end()
            ->end();

        $show
            ->tab('show.tabs_profile')
            ->with('profile', [
                'class' => 'col-md-12',
            ])
            ->add('firstName')
            ->add('lastName')
            ->end()
            ->end();

        $show
            ->tab('tabs_roles')
            ->with('roles', [
                'class' => 'col-md-12',
            ])
            ->add('roles', 'choice', [
                'multiple' => true,
                'choice_translation_domain' => 'AppBundle',
            ])
            ->end()
            ->end();
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('tabs_general')
            ->with('general', [
                'class' => 'col-md-12',
            ])
            ->add('email', EmailType::class)
            ->add('phoneNumber', TextType::class)
            ->add('plainPassword', PlainPasswordType::class, [
                'required' => null === $this->getSubject()?->getId(),
                'help' => 'form.label_help_plain_password',
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Enabled' => true,
                    'Disabled' => false,
                ]
            ])
            ->end()
            ->end();

        $form
            ->tab('form.tabs_profile')
            ->with('profile', [
                'class' => 'col-md-12',
            ])
            ->add('firstName', TextType::class, [
                'attr' => [
                    'maxlength' => 16,
                ],
            ])
            ->add('lastName', TextType::class, [
                'attr' => [
                    'maxlength' => 16,
                ],
            ])
            ->end()
            ->end();

        $form
            ->tab('tabs_roles')
            ->with('roles', [
                'class' => 'col-md-12',
            ])
            ->add('roles', RolesMatrixType::class, [
                'label' => false,
                'excluded_roles' => ['ROLE_USER', 'ROLE_SONATA_ADMIN'],
            ])
            ->end()
            ->end();
    }

    protected function prePersist(object $object): void
    {
        $this->ensureBaselineManagerRoles($object);
    }

    protected function preUpdate(object $object): void
    {
        $this->ensureBaselineManagerRoles($object);
    }

    /**
     * RolesMatrixType excludes ROLE_USER from the form; submitting the manager form
     * otherwise replaces {@see Manager::roles} with only matrix choices and drops ROLE_USER,
     * which breaks Symfony voters (AccessDeniedException: missing ROLE_USER).
     */
    private function ensureBaselineManagerRoles(object $object): void
    {
        if (!$object instanceof Manager) {
            return;
        }
        $roles = $object->getRoles();
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
            $object->setRoles(array_values(array_unique($roles)));
        }
    }
}