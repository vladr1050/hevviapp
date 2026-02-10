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

namespace FRPC\SonataAuthorization\Form\Type;

use FRPC\SonataAuthorization\Security\RolesBuilder\ExpandableRolesBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RolesMatrixType extends AbstractType
{
    private ExpandableRolesBuilderInterface $rolesBuilder;

    public function __construct(ExpandableRolesBuilderInterface $rolesBuilder)
    {
        $this->rolesBuilder = $rolesBuilder;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'expanded' => true,
            'multiple' => true,
            'choices' => function (Options $options, ?array $parentChoices): array {
                if (null !== $parentChoices && [] !== $parentChoices) {
                    return [];
                }

                $roles = $this->rolesBuilder->getRoles($options['choice_translation_domain']);
                $roles = array_keys($roles);
                $roles = array_diff($roles, $options['excluded_roles']);

                return array_combine($roles, $roles);
            },
            'choice_translation_domain' =>
                static function (Options $options, $value) {
                    // if choice_translation_domain is true, then it's the same as translation_domain
                    if (true === $value) {
                        $value = $options['translation_domain'];
                    }

                    if (null === $value) {
                        // no translation domain yet, try to ask sonata admin
                        $admin = $options['sonata_admin'] ?? null;
                        if (null === $admin && isset($options['sonata_field_description'])) {
                            $admin = $options['sonata_field_description']->getAdmin();
                        }
                        if (null !== $admin) {
                            $value = $admin->getTranslationDomain();
                        }
                    }

                    return $value;
                },
            'excluded_roles' => ['ROLE_USER'],
            'data_class' => null,
        ]);

        $resolver->addAllowedTypes('excluded_roles', 'string[]');
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'sonata_roles_matrix';
    }
}