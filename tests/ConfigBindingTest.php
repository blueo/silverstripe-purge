<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Service\NullPurgeAdaptor;
use Blueo\Purge\Service\PurgeAdaptor;
use Blueo\Purge\Service\PurgeService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * With no provider module installed, the adaptor bound by _config/purge.yml
 * is the one that refuses every operation.
 */
class ConfigBindingTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testWithNoProviderTheNullAdaptorIsBound(): void
    {
        $this->assertInstanceOf(NullPurgeAdaptor::class, Injector::inst()->get(PurgeAdaptor::class));
    }

    public function testThePurgeServiceReportsNoCapabilities(): void
    {
        $this->assertSame([], PurgeService::singleton()->getCapabilities());
    }
}
