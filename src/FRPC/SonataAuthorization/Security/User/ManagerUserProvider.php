<?php
/*
 * DOO TECHGURU Confidential
 * Copyright (C) 2022 DOO TECHGURU.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of DOO TECHGURU, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to DOO TECHGURU
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace FRPC\SonataAuthorization\Security\User;

use App\Entity\Manager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface as UserProviderInterfaceAlias;

class ManagerUserProvider implements UserProviderInterfaceAlias
{
    private string $superClass = Manager::class;

    private array $securityClasses;

    public function __construct(protected EntityManagerInterface $em, array $securityClasses = [])
    {
        $this->securityClasses = $securityClasses;
    }

    public function refreshUser(UserInterface $user): Manager
    {
        if (!$user instanceof Manager || !$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Expected an instance of %s, but got "%s".',
                Manager::class, get_class($user)));
        }

        // Check if EntityManager is still open
        if (!$this->em->isOpen()) {
            throw new \RuntimeException('EntityManager is closed. Cannot refresh user.');
        }

        if (null === $reloadedUser = $this->em->getRepository(get_class($user))->find($user->getId())) {
            throw new UserNotFoundException(sprintf('User with ID "%s" could not be reloaded.', $user->getId()));
        }

        // Check if user account is still active
        if (!$reloadedUser->isAccountIsActive()) {
            throw new UserNotFoundException(sprintf('User with ID "%s" is not active.', $user->getId()));
        }

        return $reloadedUser;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        return $this->superClass === $class || is_subclass_of($class, $this->superClass);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        /** @var null|UserInterface $user */
        if (null === $user = $this->findUserBy($identifier)) {
            throw new UserNotFoundException(sprintf('Username "%s" does not exist.', $identifier));
        }

        return $user;
    }

    private function findUserBy(string $identifier): ?object
    {

        foreach ($this->securityClasses as $className) {
            $user = $this->em->getRepository($className)->findOneBy([
                "email" => $identifier,
            ]);
            if ($user instanceof $this->superClass && $user->isAccountIsActive()) {
                return $user;
            }
        }

        return null;
    }
}
