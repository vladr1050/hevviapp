<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\NamedObject;

/**
 * Excludes PostGIS / manual / Messenger assets from schema introspection comparison.
 * Uses {@see NamedObject} so sequence names never go through deprecated {@see AbstractAsset::getName()}.
 */
final class AppSchemaAssetFilter
{
    /** @param string|AbstractAsset<\Doctrine\DBAL\Schema\Name> $assetName */
    public function __invoke(string|AbstractAsset $assetName): bool
    {
        $name = $this->toFilterableString($assetName);
        if ($name === '') {
            return true;
        }

        return ! $this->isExcluded($name);
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

    private function isExcluded(string $name): bool
    {
        if (str_starts_with($name, 'topology.')) {
            return true;
        }

        if (preg_match('/^tiger/i', $name) === 1) {
            return true;
        }

        $tail = str_contains($name, '.') ? substr($name, (int) strrpos($name, '.') + 1) : $name;

        return in_array(
            $tail,
            [
                'messenger_messages',
                'messenger_messages_id_seq',
                'invoice_day_counter',
                'order_number_seq',
            ],
            true,
        );
    }
}
