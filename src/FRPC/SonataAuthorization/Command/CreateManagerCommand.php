<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2025 SIA SLYFOX.
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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'authorization:manager:create', description: 'Create a manager')]
class CreateManagerCommand extends BaseManagerCommand
{
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('email', InputArgument::REQUIRED, 'The email'),
                new InputArgument('phone_number', InputArgument::REQUIRED, 'The phone number'),
                new InputArgument('first_name', InputArgument::REQUIRED, 'The Firstname'),
                new InputArgument('last_name', InputArgument::REQUIRED, 'The Lastname'),
                new InputArgument('password', InputArgument::REQUIRED, 'The Password'),
            ])
            ->setHelp(
                <<<'EOT'
                    The <info>%command.full_name%</info> command create a manager (so they will be able to log in):
                      <info>php bin/console %command.full_name% the@email.my</info>
                    EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $phoneNumber = $input->getArgument('phone_number');
        $firstName = $input->getArgument('first_name');
        $lastName = $input->getArgument('last_name');
        $password = $input->getArgument('password');

        $this->initManager($email, $output);

        if (null !== $this->manager) {
            $output->writeln(sprintf('User "%s" already exists.', $email));
            return 0;
        }

        $this->manager = new Manager();
        $this->manager
            ->setPhoneNumber($phoneNumber)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPlainPassword($password)
            ->setStatus(true)
            ->setRoles([
                "ROLE_ADMIN",
                "ROLE_USER",
                "ROLE_SUPER_ADMIN",
            ])
            ->setEmail($email);

        $this->save();

        $output->writeln(sprintf('User "%s" with "%s" password has been created.', $email, $password));

        return 0;
    }
}