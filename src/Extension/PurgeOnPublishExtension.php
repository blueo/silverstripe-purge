<?php

namespace Blueo\Purge\Extension;

use Blueo\Purge\Service\PurgeDispatcher;
use Blueo\Purge\Service\UrlCollector;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use Throwable;

/**
 * Purges the URLs that change when a page is published, unpublished or deleted.
 *
 * The extension is applied to SiteTree by config, and that config block is
 * gated on silverstripe/cms. This module requires silverstripe/framework alone,
 * so the owner is read through UrlCollector, which asks for a Link() and does
 * not name a class.
 *
 * Every hook catches. A CDN that is down must not stop an author publishing,
 * and the log is where a failed purge is read.
 *
 * @extends Extension<object>
 */
class PurgeOnPublishExtension extends Extension
{
    use Configurable;

    /**
     * Set to false to keep the triggers off and purge from the task or from
     * your own code.
     */
    private static bool $purge_on_change = true;

    protected function onAfterPublish(): void
    {
        $this->purge('publish');
    }

    protected function onAfterUnpublish(): void
    {
        $this->purge('unpublish');
    }

    protected function onAfterDelete(): void
    {
        $this->purge('delete');
    }

    private function purge(string $reason): void
    {
        if (!$this->config()->get('purge_on_change')) {
            return;
        }

        $owner = $this->getOwner();

        try {
            $urls = UrlCollector::singleton()->collect($owner);

            if (!$urls) {
                return;
            }

            PurgeDispatcher::singleton()->purgeUrls($urls);
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                sprintf(
                    'Purge on %s of %s #%s failed: %s',
                    $reason,
                    get_class($owner),
                    (string) ($owner->ID ?? '?'),
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }
    }
}
