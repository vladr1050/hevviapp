<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\Document\DocumentPdfRegenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:documents:regenerate-missing',
    description: 'Re-render PDF files for documents whose file_path exists in DB but file is missing on disk.',
)]
final class RegenerateMissingDocumentPdfsCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentPdfRegenerationService $regenerationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('document-id', null, InputOption::VALUE_REQUIRED, 'Regenerate a single document UUID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List missing PDFs without writing files')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Regenerate even when the PDF file already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $documents = $this->resolveDocuments($input, $io);
        if ($documents === null) {
            return Command::FAILURE;
        }

        $missing = [];
        foreach ($documents as $document) {
            if ($force || $this->regenerationService->isPdfMissingOnDisk($document)) {
                $missing[] = $document;
            }
        }

        if ($missing === []) {
            $io->success('No missing document PDFs found.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Found %d document(s) to regenerate.', \count($missing)));

        $ok = 0;
        $failed = 0;

        foreach ($missing as $document) {
            $label = sprintf(
                '%s %s (%s)',
                $document->getDocumentType()->value,
                $document->getDocumentNumber(),
                $document->getId()?->toRfc4122() ?? '?',
            );

            if ($dryRun) {
                $io->writeln('[dry-run] '.$label.' → '.$document->getFilePath());
                ++$ok;

                continue;
            }

            try {
                $this->regenerationService->regenerate($document);
                $io->writeln('<info>OK</info> '.$label);
                ++$ok;
            } catch (\Throwable $e) {
                $io->writeln('<error>FAIL</error> '.$label.': '.$e->getMessage());
                ++$failed;
            }
        }

        if ($dryRun) {
            $io->note('Dry run only — no files written. Re-run without --dry-run to regenerate.');

            return Command::SUCCESS;
        }

        if ($failed > 0) {
            $io->warning(sprintf('Regenerated %d, failed %d.', $ok, $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('Regenerated %d document PDF(s).', $ok));

        return Command::SUCCESS;
    }

    /**
     * @return list<Document>|null
     */
    private function resolveDocuments(InputInterface $input, SymfonyStyle $io): ?array
    {
        $documentId = $input->getOption('document-id');
        if (\is_string($documentId) && $documentId !== '') {
            try {
                $uuid = Uuid::fromString($documentId);
            } catch (\InvalidArgumentException) {
                $io->error('Invalid document UUID.');

                return null;
            }

            $document = $this->documentRepository->find($uuid);
            if (!$document instanceof Document) {
                $io->error('Document not found.');

                return null;
            }

            return [$document];
        }

        return $this->documentRepository->findAllWithFilePath();
    }
}
