<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
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

namespace App\Command;

use App\Entity\GeoArea;
use App\Service\GeoArea\Config\CountryConfigProvider;
use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;
use App\Service\GeoArea\DTO\OsmAreaDto;
use App\Service\GeoArea\GeoAreaParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Парсер административных юнитов из OpenStreetMap (novadi/pagasti для Латвии).
 *
 * Запускается отдельно от ParseGeoAreasCommand, чтобы не перетягивать дамп страны и cities.
 */
#[AsCommand(
    name: 'app:parse-geo-areas-admin-units',
    description: 'Parse administrative units (e.g. novadi/pagasti) from OpenStreetMap',
)]
class ParseGeoAreasAdminUnitsCommand extends Command
{
    private const KIND_MUNICIPALITY = 'municipality';
    private const KIND_PARISH = 'parish';

    public function __construct(
        private readonly GeoAreaParser $geoAreaParser,
        private readonly GeoAreaDataDumperInterface $dataDumper,
        private readonly CountryConfigProvider $countryConfigProvider,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'kind',
                InputArgument::REQUIRED,
                'Admin unit kind: municipality (e.g. novadi) or parish (e.g. pagasti)',
            )
            ->addArgument(
                'country',
                InputArgument::REQUIRED,
                'Country code (e.g. latvia)',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path for SQL dump (auto-suffixed with kind+ISO3+part)',
                'docker/dumps/geo_areas/geo_areas_admin_units_dump.sql',
            )
            ->addOption(
                'admin-level',
                null,
                InputOption::VALUE_REQUIRED,
                'Override OSM admin_level (defaults: municipality=adminLevelMunicipality, parish=adminLevelParish)',
            )
            ->addOption(
                'only-osm-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Fetch exactly one OSM relation by its ID (bypasses listing). Useful when a unit is missing because its admin_level differs in OSM.',
            )
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> imports administrative units from OSM as new GeoArea rows.

Examples:

Import Latvian novadi (admin_level=5, excludes border_type=city, scope=MUNICIPALITY):
  <info>php %command.full_name% municipality latvia</info>

Import Latvian pagasti (admin_level=7, scope=PARISH):
  <info>php %command.full_name% parish latvia</info>

Override admin_level explicitly:
  <info>php %command.full_name% municipality latvia --admin-level=5</info>

Fetch ONE specific OSM relation (e.g. Olaines novads = 13047948):
  <info>php %command.full_name% municipality latvia --only-osm-id=13047948</info>

Output: SQL files placed under docker/dumps/geo_areas/.
The header contains a commented-out TRUNCATE for the country — uncomment it if you want a clean re-import.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '384M');

        $io = new SymfonyStyle($input, $output);
        $io->title('GeoArea admin units parser (OSM)');

        $kind = (string)$input->getArgument('kind');
        if (!in_array($kind, [self::KIND_MUNICIPALITY, self::KIND_PARISH], true)) {
            $io->error(sprintf(
                'Unknown kind "%s". Allowed: %s, %s.',
                $kind,
                self::KIND_MUNICIPALITY,
                self::KIND_PARISH,
            ));

            return Command::INVALID;
        }

        $countryCode = (string)$input->getArgument('country');
        $config = $this->countryConfigProvider->getCountryConfig($countryCode);
        if ($config === null) {
            $io->error(sprintf(
                'Unknown country "%s". Available: %s.',
                $countryCode,
                implode(', ', $this->countryConfigProvider->getAvailableCountryCodes()),
            ));

            return Command::INVALID;
        }

        $adminLevelOverride = $input->getOption('admin-level');
        if ($adminLevelOverride !== null) {
            if (!ctype_digit((string)$adminLevelOverride)) {
                $io->error('--admin-level must be an integer');

                return Command::INVALID;
            }
            $adminLevel = (int)$adminLevelOverride;
        } else {
            $adminLevel = $kind === self::KIND_MUNICIPALITY
                ? $config->adminLevelMunicipality
                : $config->adminLevelParish;
        }

        $scope = $kind === self::KIND_MUNICIPALITY
            ? GeoArea::SCOPE['MUNICIPALITY']
            : GeoArea::SCOPE['PARISH'];

        $onlyOsmId = $input->getOption('only-osm-id');
        if ($onlyOsmId !== null) {
            $onlyOsmId = trim((string)$onlyOsmId);
            if (!ctype_digit($onlyOsmId)) {
                $io->error('--only-osm-id must be a numeric OSM relation ID');

                return Command::INVALID;
            }
        }

        $io->section('Parameters');
        $io->definitionList(
            ['Country' => sprintf('%s (%s)', $config->name, $config->iso3Code)],
            ['OSM relation' => $config->osmRelationId],
            ['Kind' => $kind],
            ['admin_level' => $onlyOsmId !== null ? 'ignored (direct fetch)' : $adminLevel],
            ['Scope id' => $scope],
            ['Only OSM ID' => $onlyOsmId ?? '—'],
        );

        $outputPath = (string)$input->getOption('output');
        if (!str_starts_with($outputPath, '/')) {
            $outputPath = $this->projectDir . '/' . $outputPath;
        }
        $outputPath = $this->injectKindIntoPath($outputPath, $kind);
        if ($onlyOsmId !== null) {
            $outputPath = $this->appendSuffixToPath($outputPath, 'osm' . $onlyOsmId);
        }
        $io->info('Output base path: ' . $outputPath);

        if (!$io->confirm('Start parsing?', true)) {
            $io->warning('Parsing cancelled');

            return Command::SUCCESS;
        }

        try {
            $io->section('Fetching from Overpass...');

            if ($onlyOsmId !== null) {
                /** @var OsmAreaDto[] $units */
                $units = $this->geoAreaParser->parseSingleAdminUnit(
                    $config,
                    $onlyOsmId,
                    $scope,
                    $kind,
                );
            } else {
                /** @var OsmAreaDto[] $units */
                $units = $kind === self::KIND_MUNICIPALITY
                    ? $this->geoAreaParser->parseMunicipalities($this->withAdminLevel($config, $adminLevel, self::KIND_MUNICIPALITY))
                    : $this->geoAreaParser->parseParishes($this->withAdminLevel($config, $adminLevel, self::KIND_PARISH));
            }

            if (empty($units)) {
                $io->warning('Overpass returned no units. Nothing to dump.');

                return Command::SUCCESS;
            }

            $io->success(sprintf('%d %s parsed', count($units), $kind === self::KIND_MUNICIPALITY ? 'municipalities' : 'parishes'));

            $io->section('Generating SQL dump...');
            $this->dataDumper->generateSqlDump($units, $outputPath);

            $io->success([
                'Done.',
                sprintf('Total units: %d', count($units)),
                sprintf('SQL dump base: %s', $outputPath),
                'Apply with: psql ... < <generated_part>.sql (or load via docker/dumps loader).',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Parsing failed!',
                'Error: ' . $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Build a config copy with the admin level used by the chosen kind overridden.
     */
    private function withAdminLevel($config, int $adminLevel, string $kind)
    {
        return new \App\Service\GeoArea\DTO\CountryConfigDto(
            name: $config->name,
            iso3Code: $config->iso3Code,
            osmRelationId: $config->osmRelationId,
            adminLevelCity: $config->adminLevelCity,
            adminLevelMunicipality: $kind === self::KIND_MUNICIPALITY ? $adminLevel : $config->adminLevelMunicipality,
            adminLevelParish: $kind === self::KIND_PARISH ? $adminLevel : $config->adminLevelParish,
            municipalityExcludeBorderType: $config->municipalityExcludeBorderType,
        );
    }

    /**
     * Insert kind suffix into base output filename so municipality and parish dumps don't collide.
     */
    private function injectKindIntoPath(string $path, string $kind): string
    {
        $info = pathinfo($path);
        $dir = $info['dirname'] ?? '.';
        $base = $info['filename'] ?? 'geo_areas_admin_units_dump';
        $ext = $info['extension'] ?? 'sql';

        return sprintf('%s/%s_%s.%s', $dir, $base, $kind, $ext);
    }

    /**
     * Append an arbitrary suffix to the filename (keeps directory + extension).
     */
    private function appendSuffixToPath(string $path, string $suffix): string
    {
        $info = pathinfo($path);
        $dir = $info['dirname'] ?? '.';
        $base = $info['filename'] ?? 'geo_areas_admin_units_dump';
        $ext = $info['extension'] ?? 'sql';

        return sprintf('%s/%s_%s.%s', $dir, $base, $suffix, $ext);
    }
}
