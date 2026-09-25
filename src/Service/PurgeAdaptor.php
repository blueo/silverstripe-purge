<?php

namespace Blueo\Purge\Service;

use Blueo\Purge\Exception\UnsupportedOperationException;

/**
 * The contract a provider module implements.
 *
 * A site calls PurgeService and never this interface. An implementation is
 * bound over this name through Injector by a provider module, which is how a
 * site changes CDN without changing its own code.
 *
 * An implementation declares what it does through supports(). A method whose
 * capability the implementation does not declare throws
 * UnsupportedOperationException. It does not return a failed result, because a
 * result is a record of an attempt and no attempt was made.
 */
interface PurgeAdaptor
{
    public function supports(PurgeCapability $capability): bool;

    /**
     * @param array<string> $urls Paths or absolute URLs on the site.
     * @throws UnsupportedOperationException
     */
    public function purgeUrls(array $urls): PurgeResult;

    /**
     * @throws UnsupportedOperationException
     */
    public function purgeAll(): PurgeResult;

    /**
     * @param array<string> $tags Surrogate keys, as the provider names them.
     * @throws UnsupportedOperationException
     */
    public function purgeTags(array $tags): PurgeResult;
}
