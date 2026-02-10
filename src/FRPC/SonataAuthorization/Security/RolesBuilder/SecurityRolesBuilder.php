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

namespace FRPC\SonataAuthorization\Security\RolesBuilder;

use Sonata\AdminBundle\SonataConfiguration;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityRolesBuilder implements ExpandableRolesBuilderInterface
{
    private AuthorizationCheckerInterface $authorizationChecker;

    private SonataConfiguration $configuration;

    private TranslatorInterface $translator;

    private array $rolesHierarchy;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        SonataConfiguration $configuration,
        TranslatorInterface $translator,
        array $rolesHierarchy = []
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->configuration = $configuration;
        $this->translator = $translator;
        $this->rolesHierarchy = $rolesHierarchy;
    }

    public function getExpandedRoles(?string $domain = null): array
    {
        $securityRoles = [];
        $childRoles = [];
        $hierarchy = $this->getHierarchy();

        foreach ($hierarchy as $role => $childRole) {
            $translatedRoles = array_map(
                $this->translateRole(...),
                $childRole,
                array_fill(0, \count($childRole), $domain)
            );

            $translatedRoles = \count($translatedRoles) > 0 ? ': '.implode(', ', $translatedRoles) : '';
            $securityRoles[$role] = [
                'role' => $role,
                'role_translated' => $role.$translatedRoles,
                'is_granted' => $this->authorizationChecker->isGranted($role),
            ];

            $childRoles[] = $this->getSecurityRoles($hierarchy, $childRoles, $domain);
        }

        return array_merge(
            $securityRoles,
            $childRoles
        );
    }

    public function getRoles(?string $domain = null): array
    {
        $securityRoles = [];
        $childRoles = [];
        $hierarchy = $this->getHierarchy();

        foreach ($hierarchy as $role => $childRole) {
            $securityRoles[$role] = $this->getSecurityRole($role, $domain);
            $childRoles[] = $this->getSecurityRoles($hierarchy, $childRole, $domain);
        }

        return array_merge(
            $securityRoles,
            $childRoles,
        );
    }

    private function getHierarchy(): array
    {
        $roleSuperAdmin = $this->configuration->getOption('role_super_admin');
        \assert(\is_string($roleSuperAdmin));

        $roleAdmin = $this->configuration->getOption('role_admin');
        \assert(\is_string($roleAdmin));

        return array_merge([
            $roleSuperAdmin => [],
            $roleAdmin => [],
        ], $this->rolesHierarchy);
    }

    private function getSecurityRole(string $role, ?string $domain): array
    {
        return [
            'role' => $role,
            'role_translated' => $this->translateRole($role, $domain),
            'is_granted' => $this->authorizationChecker->isGranted($role),
        ];
    }

    private function getSecurityRoles(array $hierarchy, array $roles, ?string $domain): array
    {
        $securityRoles = [];
        foreach ($roles as $role) {
            if (!\array_key_exists($role, $hierarchy) && !isset($securityRoles[$role])
                && !$this->recursiveArraySearch($role, $securityRoles)) {
                $securityRoles[$role] = $this->getSecurityRole($role, $domain);
            }
        }

        return $securityRoles;
    }

    private function translateRole(string $role, ?string $domain): string
    {
        if (null !== $domain) {
            return $this->translator->trans($role, [], $domain);
        }

        return $role;
    }

    private function recursiveArraySearch(string $role, array $roles): bool
    {
        foreach ($roles as $key => $value) {
            if ($role === $key || (\is_array($value) && true === $this->recursiveArraySearch($role, $value))) {
                return true;
            }
        }

        return false;
    }
}