<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Name\NamedObject;

/**
 * Same rules as DoctrineBundle's RegexSchemaAssetFilter, but resolves {@see NamedObject}
 * without calling deprecated {@see AbstractAsset::getName()} (e.g. for sequences).
 */
final class AppSchemaAssetFilter
{
    public function __construct(
        private readonly string $filterExpression,
    ) {
    }

    /** @param string|AbstractAsset<\Doctrine\DBAL\Schema\Name> $assetName */
    public function __invoke(string|AbstractAsset $assetName): bool
    {
        return (bool) preg_match($this->filterExpression, $this->toFilterableString($assetName));
    }

    /** @param string|AbstractAsset<\Doctrine\DBAL\Schema\Name> $assetName */
    private function toFilterableString(string|AbstractAsset $assetName): string
    {
        if (is_string($assetName)) {
            return $assetName;
        }

        if ($assetName instanceof NamedObject) {
            return $assetName->getObjectName()->toString();
        }

        return '';
    }
}
