<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Exception\UnsupportedOperationException;
use Blueo\Purge\Service\PurgeAdaptor;
use Blueo\Purge\Service\PurgeCapability;
use Blueo\Purge\Service\PurgeDispatcher;
use Blueo\Purge\Service\PurgeService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class PurgeDispatcherTest extends SapphireTest
{
    protected $usesDatabase = false;

    private FakePurgeAdaptor $adaptor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adaptor = new FakePurgeAdaptor();
        Injector::inst()->registerService($this->adaptor, PurgeAdaptor::class);
        PurgeService::singleton()->setAdaptor($this->adaptor);
    }

    public function testWithoutQueuedjobsThePurgeIsSentInline(): void
    {
        $this->assertFalse(PurgeDispatcher::singleton()->canQueue());

        $result = PurgeDispatcher::singleton()->purgeUrls(['/news/']);

        $this->assertNotNull($result);
        $this->assertTrue($result->isSuccessful());
        $this->assertSame([['/news/']], $this->adaptor->purgedUrls);
    }

    public function testPurgeAllIsSentInline(): void
    {
        PurgeDispatcher::singleton()->purgeAll();

        $this->assertSame(1, $this->adaptor->purgeAllCalls);
    }

    public function testQueuingIsOffWhenConfigTurnsItOff(): void
    {
        PurgeDispatcher::config()->set('use_queue', false);

        $this->assertFalse(PurgeDispatcher::singleton()->canQueue());
    }

    public function testAnUnsupportedOperationIsRefusedBeforeAnythingIsQueued(): void
    {
        $this->adaptor->capabilities = [PurgeCapability::PurgeUrls];

        $this->expectException(UnsupportedOperationException::class);

        PurgeDispatcher::singleton()->purgeTags(['news']);
    }
}
