<?php

declare(strict_types=1);

namespace FRPC\SonataAuthorization\Command;

use FRPC\SonataAuthorization\Command\BaseManagerCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'authorization:manager:remove', description: 'Remove a user')]
class RemoveManagerCommand extends BaseManagerCommand
{
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('email', InputArgument::REQUIRED, 'The email'),
            ])
            ->setHelp(
                <<<'EOT'
                    The <info>%command.full_name%</info> command removed a manager (so they will be able to log in):
                      <info>php bin/console %command.full_name% the@email.my</info>
                    EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');

        $this->initManager($email, $output);

        if (null === $this->manager) {
            return 0;
        }
        $this->remove();

        $output->writeln(sprintf('User "%s" has been removed.', $email));

        return 0;
    }
}
