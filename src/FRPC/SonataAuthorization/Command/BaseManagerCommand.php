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

namespace FRPC\SonataAuthorization\Command;

use App\Entity\Manager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class BaseManagerCommand extends Command
{
    private string $superClass = Manager::class;

    protected ?Manager $manager = null;

    private array $securityClasses;

    public function __construct(protected EntityManagerInterface $em, array $securityClasses = [])
    {
        $this->securityClasses = $securityClasses;

        if (empty($securityClasses)) {
            $this->securityClasses[] = Manager::class;
        }

        parent::__construct();
    }

    protected function initManager(string $username, OutputInterface $output): void
    {
        foreach ($this->securityClasses as $className) {
            $this->manager = $this->em->getRepository($className)->findOneBy([
                "email" => $username,
            ]);

            if ($this->manager instanceof $this->superClass) {
                return;
            }
        }

        if (null === $this->manager) {
            $output->writeln(sprintf('User identified by "%s" username does not exist.', $username));
        }
    }

    protected function save(): void
    {
        $this->em->persist($this->manager);
        $this->em->flush();
    }

    protected function remove(): void
    {
        $this->em->remove($this->manager);
        $this->em->flush();
    }
}