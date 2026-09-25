<?php

namespace Blueo\Purge\Service;

/**
 * The operations a purge provider may offer.
 *
 * Few providers offer all three, and what a provider offers is a fact about
 * that provider and its account rather than about this enum, so nothing is
 * asserted here about any particular CDN. A provider declares what it does
 * through PurgeAdaptor::supports(), and PurgeService refuses an operation the
 * provider did not declare.
 *
 * The set is an enum rather than one interface for each operation, for two
 * reasons. A caller can list the cases and ask a provider about each one, so
 * the answer is available before the call and can be printed by a task. And
 * the refusal happens in one place, so a provider cannot answer an operation
 * it does not perform with a value that reads as success.
 */
enum PurgeCapability: string
{
    case PurgeUrls = 'purgeUrls';

    case PurgeAll = 'purgeAll';

    case PurgeTags = 'purgeTags';

    public function getLabel(): string
    {
        return match ($this) {
            self::PurgeUrls => 'Purge by URL',
            self::PurgeAll => 'Purge everything',
            self::PurgeTags => 'Purge by tag',
        };
    }
}
