<?php

namespace Blueo\Purge\Service;

use Blueo\Purge\Exception\UnsupportedOperationException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injectable;
use Throwable;

/**
 * The entry point a site calls.
 *
 * It holds the capability gate and the logging, so both are the same whichever
 * provider is bound. A provider that forgot to check a capability still cannot
 * run an operation it did not declare, because the check happens here.
 *
 * Calls are synchronous. Use PurgeDispatcher to send a purge from a job.
 */
class PurgeService
{
    use Injectable;

    private ?PurgeAdaptor $adaptor = null;

    private ?LoggerInterface $logger = null;

    private static array $dependencies = [
        'adaptor' => '%$' . PurgeAdaptor::class,
        'logger' => '%$' . LoggerInterface::class,
    ];

    public function setAdaptor(?PurgeAdaptor $adaptor): void
    {
        $this->adaptor = $adaptor;
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getAdaptor(): ?PurgeAdaptor
    {
        return $this->adaptor;
    }

    public function supports(PurgeCapability $capability): bool
    {
        return $this->adaptor !== null && $this->adaptor->supports($capability);
    }

    /**
     * Every capability the bound provider declares.
     *
     * A task prints this. A site reads it to decide whether to build a feature
     * on tag purging.
     *
     * @return array<PurgeCapability>
     */
    public function getCapabilities(): array
    {
        return array_values(array_filter(
            PurgeCapability::cases(),
            fn (PurgeCapability $capability): bool => $this->supports($capability)
        ));
    }

    /**
     * @param array<string> $urls
     * @throws UnsupportedOperationException
     */
    public function purgeUrls(array $urls): PurgeResult
    {
        $this->assertSupports(PurgeCapability::PurgeUrls);

        $urls = array_values(array_filter(array_unique($urls), fn ($url): bool => trim((string) $url) !== ''));

        if (!$urls) {
            return PurgeResult::success(null, 'No URLs to purge.');
        }

        return $this->run(PurgeCapability::PurgeUrls, fn (): PurgeResult => $this->adaptor->purgeUrls($urls));
    }

    /**
     * @throws UnsupportedOperationException
     */
    public function purgeAll(): PurgeResult
    {
        $this->assertSupports(PurgeCapability::PurgeAll);

        return $this->run(PurgeCapability::PurgeAll, fn (): PurgeResult => $this->adaptor->purgeAll());
    }

    /**
     * @param array<string> $tags
     * @throws UnsupportedOperationException
     */
    public function purgeTags(array $tags): PurgeResult
    {
        $this->assertSupports(PurgeCapability::PurgeTags);

        $tags = array_values(array_filter(array_unique($tags), fn ($tag): bool => trim((string) $tag) !== ''));

        if (!$tags) {
            return PurgeResult::success(null, 'No tags to purge.');
        }

        return $this->run(PurgeCapability::PurgeTags, fn (): PurgeResult => $this->adaptor->purgeTags($tags));
    }

    /**
     * Throw unless the bound provider declares the capability.
     *
     * Public so a caller that queues work can refuse it before the queue,
     * rather than in a job an hour later.
     *
     * @throws UnsupportedOperationException
     */
    public function assertSupports(PurgeCapability $capability): void
    {
        if ($this->adaptor === null) {
            throw new UnsupportedOperationException(
                'No purge adaptor is bound. Install a provider module for blueo/silverstripe-purge.'
            );
        }

        if ($this->adaptor->supports($capability)) {
            return;
        }

        throw new UnsupportedOperationException(sprintf(
            '%s is not supported by %s. Ask %s::supports() before you call it.',
            $capability->getLabel(),
            get_class($this->adaptor),
            PurgeService::class
        ));
    }

    /**
     * Run one adaptor call and record what happened.
     *
     * A provider that throws is turned into a failed result, so one purge
     * cannot take a publish down with it. The exception reaches the log, which
     * is the only place a failed purge is visible.
     */
    private function run(PurgeCapability $capability, callable $call): PurgeResult
    {
        try {
            $result = $call();
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf('Purge failed (%s): %s', $capability->value, $e->getMessage()),
                ['exception' => $e]
            );

            return PurgeResult::failure($e->getMessage());
        }

        if (!$result->isSuccessful()) {
            $this->logger?->error(sprintf('Purge failed (%s): %s', $capability->value, $result->getMessage()));
        }

        return $result;
    }
}
