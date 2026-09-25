<?php

namespace Blueo\Purge\Job;

use Blueo\Purge\Service\PurgeCapability;
use Blueo\Purge\Service\PurgeService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Sends one purge from the queue.
 *
 * This class is loaded only when silverstripe/queuedjobs is installed.
 * PurgeDispatcher reaches it by name and asks for QueuedJobService first, so
 * on a site without queuedjobs the file is never autoloaded and the missing
 * parent class is never looked for.
 *
 * The job does not throw on a failed purge. PurgeService returns a failed
 * result and writes the reason to the log. A job that throws is retried, and a
 * CDN that refuses a path refuses it again.
 */
class PurgeJob extends AbstractQueuedJob
{
    public function __construct(string $operation = '', array $items = [])
    {
        $this->operation = $operation;
        $this->items = $items;
        $this->totalSteps = 1;
    }

    public function getTitle(): string
    {
        $capability = PurgeCapability::tryFrom((string) $this->operation);
        $label = $capability ? $capability->getLabel() : (string) $this->operation;
        $items = (array) $this->items;

        if (!$items) {
            return sprintf('Purge: %s', $label);
        }

        return sprintf('Purge: %s (%d item(s))', $label, count($items));
    }

    /**
     * Collapses a repeat of the same purge queued before this one runs.
     *
     * Ten pages published in a minute produce ten jobs, and each path over the
     * free monthly allowance is charged.
     */
    public function getSignature(): string
    {
        $items = (array) $this->items;
        sort($items);

        return md5(implode('|', array_merge([(string) $this->operation], $items)));
    }

    public function process(): void
    {
        $service = PurgeService::singleton();
        $capability = PurgeCapability::tryFrom((string) $this->operation);
        $items = array_map('strval', (array) $this->items);

        $result = match ($capability) {
            PurgeCapability::PurgeUrls => $service->purgeUrls($items),
            PurgeCapability::PurgeAll => $service->purgeAll(),
            PurgeCapability::PurgeTags => $service->purgeTags($items),
            default => null,
        };

        if ($result === null) {
            $this->addMessage(sprintf('Unknown purge operation "%s".', (string) $this->operation));
        } else {
            $this->addMessage((string) $result);
        }

        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
