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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'authorization:manager:change-password', description: 'Change the password for manager')]
final class ChangePasswordCommand extends BaseManagerCommand
{
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('email', InputArgument::REQUIRED, 'The email'),
                new InputArgument('password', InputArgument::REQUIRED, 'The password'),
            ])
            ->setHelp(
                <<<'EOT'
                    The <info>%command.full_name%</info> command changes the password of a manager:
                      <info>php bin/console %command.full_name% the@email.my mypassword</info>
                    EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('email');
        $password = $input->getArgument('password');

        $this->initManager($username, $output);

        if (null === $this->manager) {
            return 0;
        }

        $this->manager->setPlainPassword($password);

        $this->save();

        $output->writeln(sprintf('Changed password for manager "%s".', $username));

        return 0;
    }
}