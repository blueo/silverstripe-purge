<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Exception\UnsupportedOperationException;
use Blueo\Purge\Service\PurgeAdaptor;
use Blueo\Purge\Service\PurgeCapability;
use Blueo\Purge\Service\PurgeResult;
use SilverStripe\Core\Injector\Injectable;

/**
 * An adaptor that records what it was asked for and declares what a test sets.
 */
class FakePurgeAdaptor implements PurgeAdaptor
{
    use Injectable;

    /** @var array<PurgeCapability> */
    public array $capabilities = [PurgeCapability::PurgeUrls, PurgeCapability::PurgeAll];

    public array $purgedUrls = [];

    public int $purgeAllCalls = 0;

    public array $purgedTags = [];

    public ?PurgeResult $nextResult = null;

    public ?string $throwMessage = null;

    public function supports(PurgeCapability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function purgeUrls(array $urls): PurgeResult
    {
        $this->purgedUrls[] = $urls;

        return $this->respond(count($urls));
    }

    public function purgeAll(): PurgeResult
    {
        $this->purgeAllCalls++;

        return $this->respond(1);
    }

    public function purgeTags(array $tags): PurgeResult
    {
        $this->purgedTags[] = $tags;

        return $this->respond(count($tags));
    }

    private function respond(int $count): PurgeResult
    {
        if ($this->throwMessage !== null) {
            throw new UnsupportedOperationException($this->throwMessage);
        }

        return $this->nextResult ?? PurgeResult::success('FAKE1', '', $count);
    }
}
