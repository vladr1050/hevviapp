<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\NotificationRule;
use App\Notification\NotificationRuleDefaults;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notification:seed-rules',
    description: 'Insert default MVP notification rules if the table is empty, or add missing defaults with --ensure-missing.',
)]
final class SeedNotificationRulesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'ensure-missing',
            null,
            InputOption::VALUE_NONE,
            'Insert any default rule that does not yet exist (matched by event_key + name).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(NotificationRule::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();

        $ensureMissing = (bool) $input->getOption('ensure-missing');

        if ($count > 0 && !$ensureMissing) {
            $io->warning(sprintf('notification_rule already has %d row(s). Skipping seed (use --ensure-missing to add new defaults).', $count));

            return Command::SUCCESS;
        }

        $repo = $this->em->getRepository(NotificationRule::class);
        $added = 0;

        foreach (NotificationRuleDefaults::all() as $row) {
            $exists = $repo->findOneBy([
                'eventKey' => $row['eventKey'],
                'name' => $row['name'],
            ]);
            if ($exists instanceof NotificationRule) {
                continue;
            }

            $rule = new NotificationRule();
            $rule->setName($row['name']);
            $rule->setDescription($row['description']);
            $rule->setEventKey($row['eventKey']);
            $rule->setRecipientType($row['recipientType']);
            $rule->setSubjectTemplate($row['subjectTemplate']);
            $rule->setBodyTemplate($row['bodyTemplate']);
            $rule->setAttachInvoicePdf($row['attachInvoicePdf']);
            $rule->setIsActive(true);
            $rule->setSendOncePerOrder($row['sendOncePerOrder']);
            $this->em->persist($rule);
            ++$added;
        }

        if ($added === 0) {
            $io->success('No new default rules to insert (all already present).');

            return Command::SUCCESS;
        }

        $this->em->flush();

        $io->success(sprintf('Inserted %d notification rule(s).', $added));

        return Command::SUCCESS;
    }
}
