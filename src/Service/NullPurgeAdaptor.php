<?php

namespace Blueo\Purge\Service;

use Blueo\Purge\Exception\UnsupportedOperationException;
use SilverStripe\Core\Injector\Injectable;

/**
 * The adaptor in place when no provider module is installed.
 *
 * It declares no capability, so every call throws. This module holds the
 * interfaces and the triggers. It purges nothing on its own, and a site that
 * installs it alone must find that out at the first call rather than after a
 * month of stale pages.
 */
class NullPurgeAdaptor implements PurgeAdaptor
{
    use Injectable;

    public function supports(PurgeCapability $capability): bool
    {
        return false;
    }

    public function purgeUrls(array $urls): PurgeResult
    {
        throw $this->noProvider(PurgeCapability::PurgeUrls);
    }

    public function purgeAll(): PurgeResult
    {
        throw $this->noProvider(PurgeCapability::PurgeAll);
    }

    public function purgeTags(array $tags): PurgeResult
    {
        throw $this->noProvider(PurgeCapability::PurgeTags);
    }

    private function noProvider(PurgeCapability $capability): UnsupportedOperationException
    {
        return new UnsupportedOperationException(sprintf(
            '%s was asked for, and no purge provider is installed. Install a provider module, '
                . 'for example blueo/silverstripe-purge-cloudfront, and set the environment variable it reads.',
            $capability->getLabel()
        ));
    }
}
