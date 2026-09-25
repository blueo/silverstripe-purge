<?php

namespace Blueo\Purge\Service;

use Blueo\Purge\Exception\UnsupportedOperationException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * Decides when a purge is sent.
 *
 * An author who publishes a page waits for the response. A purge is an HTTP
 * call to a CDN and it can take seconds, so the publish must not hold it. When
 * silverstripe/queuedjobs is installed the purge goes to a job and the publish
 * returns. When it is not, the purge is sent in the same request.
 *
 * queuedjobs is a suggestion in composer.json and not a requirement, so the
 * class is reached by name and never imported. A site without queuedjobs
 * purges inline and is told so by canQueue().
 */
class PurgeDispatcher
{
    use Injectable;
    use Configurable;

    /**
     * Set to false to send every purge inline, including on a site that has
     * queuedjobs. Use it while you are reading purge failures in a terminal.
     */
    private static bool $use_queue = true;

    private const QUEUED_JOB_SERVICE = 'Symbiote\\QueuedJobs\\Services\\QueuedJobService';

    private const PURGE_JOB = 'Blueo\\Purge\\Job\\PurgeJob';

    /**
     * @param array<string> $urls
     * @throws UnsupportedOperationException
     * @return PurgeResult|null Null when the purge was queued.
     */
    public function purgeUrls(array $urls): ?PurgeResult
    {
        return $this->dispatch(PurgeCapability::PurgeUrls, $urls);
    }

    /**
     * @throws UnsupportedOperationException
     */
    public function purgeAll(): ?PurgeResult
    {
        return $this->dispatch(PurgeCapability::PurgeAll, []);
    }

    /**
     * @param array<string> $tags
     * @throws UnsupportedOperationException
     */
    public function purgeTags(array $tags): ?PurgeResult
    {
        return $this->dispatch(PurgeCapability::PurgeTags, $tags);
    }

    public function canQueue(): bool
    {
        return $this->config()->get('use_queue') && class_exists(self::QUEUED_JOB_SERVICE);
    }

    /**
     * @param array<string> $items
     * @throws UnsupportedOperationException
     */
    private function dispatch(PurgeCapability $capability, array $items): ?PurgeResult
    {
        $service = PurgeService::singleton();

        // Checked here as well as inside PurgeService, so an unsupported
        // operation is refused where the caller can see it. A job queued and
        // then refused an hour later reports against the job and not against
        // the publish that asked for it.
        $service->assertSupports($capability);

        if (!$this->canQueue()) {
            return match ($capability) {
                PurgeCapability::PurgeUrls => $service->purgeUrls($items),
                PurgeCapability::PurgeAll => $service->purgeAll(),
                PurgeCapability::PurgeTags => $service->purgeTags($items),
            };
        }

        $job = Injector::inst()->create(self::PURGE_JOB, $capability->value, $items);
        Injector::inst()->get(self::QUEUED_JOB_SERVICE)->queueJob($job);

        return null;
    }
}
