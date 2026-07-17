<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\User;
use App\Service\Order\TestAccountEmails;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mark-test-accounts',
    description: 'Mark configured test senders/carriers and their orders with is_test=true',
)]
final class MarkTestAccountsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would change without writing to the database',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $senderEmails = array_map('strtolower', TestAccountEmails::SENDERS);
        $carrierEmails = array_map('strtolower', TestAccountEmails::CARRIERS);

        $users = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) IN (:emails)')
            ->setParameter('emails', $senderEmails)
            ->getQuery()
            ->getResult();

        $carriers = $this->em->getRepository(Carrier::class)->createQueryBuilder('c')
            ->andWhere('LOWER(c.email) IN (:emails)')
            ->setParameter('emails', $carrierEmails)
            ->getQuery()
            ->getResult();

        $usersMarked = 0;
        foreach ($users as $user) {
            /** @var User $user */
            if (!$user->isTest()) {
                $io->writeln(sprintf('User %s → is_test=true', $user->getEmail()));
                if (!$dryRun) {
                    $user->setIsTest(true);
                }
                ++$usersMarked;
            }
        }

        $carriersMarked = 0;
        foreach ($carriers as $carrier) {
            /** @var Carrier $carrier */
            if (!$carrier->isTest()) {
                $io->writeln(sprintf('Carrier %s → is_test=true', $carrier->getEmail()));
                if (!$dryRun) {
                    $carrier->setIsTest(true);
                }
                ++$carriersMarked;
            }
        }

        $ordersQb = $this->em->getRepository(Order::class)->createQueryBuilder('o')
            ->innerJoin('o.sender', 's')
            ->andWhere('o.isTest = false')
            ->andWhere('LOWER(s.email) IN (:emails)')
            ->setParameter('emails', $senderEmails);

        $orders = $ordersQb->getQuery()->getResult();
        $ordersMarked = 0;
        foreach ($orders as $order) {
            /** @var Order $order */
            $io->writeln(sprintf('Order %s (sender %s) → is_test=true', $order->getReference(), $order->getSender()?->getEmail()));
            if (!$dryRun) {
                $order->setIsTest(true);
            }
            ++$ordersMarked;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%sMarked users=%d, carriers=%d, orders=%d. Missing senders: %s. Missing carriers: %s.',
            $dryRun ? '[dry-run] ' : '',
            $usersMarked,
            $carriersMarked,
            $ordersMarked,
            $this->missingEmails($senderEmails, $users),
            $this->missingEmails($carrierEmails, $carriers),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $expected
     * @param list<User|Carrier> $found
     */
    private function missingEmails(array $expected, array $found): string
    {
        $foundEmails = array_map(
            static fn (User|Carrier $entity): string => strtolower((string) $entity->getEmail()),
            $found,
        );
        $missing = array_values(array_diff($expected, $foundEmails));

        return $missing === [] ? 'none' : implode(', ', $missing);
    }
}
