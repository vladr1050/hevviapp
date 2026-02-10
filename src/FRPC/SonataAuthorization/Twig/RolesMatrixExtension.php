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

namespace FRPC\SonataAuthorization\Twig;

use FRPC\SonataAuthorization\Security\RolesBuilder\MatrixRolesBuilderInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RolesMatrixExtension extends AbstractExtension
{
    private MatrixRolesBuilderInterface $rolesBuilder;

    public function __construct(MatrixRolesBuilderInterface $rolesBuilder)
    {
        $this->rolesBuilder = $rolesBuilder;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'renderMatrix',
                $this->renderMatrix(...),
                ['needs_environment' => true]),
            new TwigFunction(
                'renderRolesList',
                $this->renderRolesList(...),
                ['needs_environment' => true]
            ),
        ];
    }

    public function renderRolesList(Environment $environment, FormView $form): string
    {
        $roles = $this->rolesBuilder->getRoles($form->vars['choice_translation_domain']);
        foreach ($roles as $role => $attributes) {
            if (isset($attributes['admin_label'])) {
                unset($roles[$role]);
                continue;
            }

            $roles[$role] = $attributes;
            foreach ($form->getIterator() as $child) {
                if ($child->vars['value'] === $role) {
                    $roles[$role]['form'] = $child;
                }
            }
        }

        return $environment->render('@SonataAuthorization/Admin/Form/Security/role_matrix_list.html.twig', [
            'roles' => $roles,
        ]);
    }

    public function renderMatrix(Environment $environment, FormView $form): string
    {
        $groupedRoles = [];
        foreach ($this->rolesBuilder->getRoles($form->vars['choice_translation_domain']) as $role => $attributes) {
            if (!isset($attributes['admin_label'])) {
                continue;
            }

            $groupedRoles[$attributes['admin_label']][$role] = $attributes;
            foreach ($form->getIterator() as $child) {
                if ($child->vars['value'] === $role) {
                    $groupedRoles[$attributes['admin_label']][$role]['form'] = $child;
                }
            }
        }

        return $environment->render('@SonataAuthorization/Admin/Form/Security/roles_matrix.html.twig', [
            'grouped_roles' => $groupedRoles,
            'permission_labels' => $this->rolesBuilder->getPermissionLabels(),
        ]);
    }
}