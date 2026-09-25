<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Exception\UnsupportedOperationException;
use Blueo\Purge\Service\NullPurgeAdaptor;
use Blueo\Purge\Service\PurgeCapability;
use Blueo\Purge\Service\PurgeResult;
use Blueo\Purge\Service\PurgeService;
use SilverStripe\Dev\SapphireTest;

class CapabilityTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function service(FakePurgeAdaptor $adaptor): PurgeService
    {
        $service = PurgeService::create();
        $service->setAdaptor($adaptor);

        return $service;
    }

    public function testAnUndeclaredCapabilityIsRefusedRatherThanIgnored(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->capabilities = [PurgeCapability::PurgeUrls, PurgeCapability::PurgeAll];
        $service = $this->service($adaptor);

        $this->assertFalse($service->supports(PurgeCapability::PurgeTags));

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageMatches('/Purge by tag is not supported/');

        $service->purgeTags(['news']);
    }

    public function testARefusedOperationNeverReachesTheAdaptor(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->capabilities = [PurgeCapability::PurgeUrls];
        $service = $this->service($adaptor);

        try {
            $service->purgeTags(['news']);
        } catch (UnsupportedOperationException) {
            // The assertion is that nothing was purged.
        }

        $this->assertSame([], $adaptor->purgedTags);
    }

    public function testADeclaredCapabilityIsPassedThrough(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->capabilities = PurgeCapability::cases();
        $service = $this->service($adaptor);

        $result = $service->purgeTags(['news', 'events']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([['news', 'events']], $adaptor->purgedTags);
    }

    public function testCapabilitiesAreListedBeforeAnyCallIsMade(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->capabilities = [PurgeCapability::PurgeUrls, PurgeCapability::PurgeAll];

        $this->assertSame(
            [PurgeCapability::PurgeUrls, PurgeCapability::PurgeAll],
            $this->service($adaptor)->getCapabilities()
        );
    }

    public function testAssertSupportsThrowsForTheSameCapabilityTheCallWouldRefuse(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->capabilities = [];

        $this->expectException(UnsupportedOperationException::class);

        $this->service($adaptor)->assertSupports(PurgeCapability::PurgeAll);
    }

    public function testWithNoProviderInstalledEveryCapabilityIsRefused(): void
    {
        $service = PurgeService::create();
        $service->setAdaptor(new NullPurgeAdaptor());

        $this->assertSame([], $service->getCapabilities());

        $this->expectException(UnsupportedOperationException::class);

        $service->purgeAll();
    }

    public function testTheNullAdaptorNamesTheMissingProvider(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessageMatches('/no purge provider is installed/');

        (new NullPurgeAdaptor())->purgeUrls(['/']);
    }

    public function testAnEmptyUrlListIsStillCapabilityChecked(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->capabilities = [];

        $this->expectException(UnsupportedOperationException::class);

        $this->service($adaptor)->purgeUrls([]);
    }

    public function testAnEmptyUrlListDoesNotReachTheProvider(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $service = $this->service($adaptor);

        $result = $service->purgeUrls(['', '   ']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('No URLs to purge.', $result->getMessage());
        $this->assertSame([], $adaptor->purgedUrls);
    }

    public function testDuplicateUrlsAreSentOnce(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $this->service($adaptor)->purgeUrls(['/a/', '/a/', '/b/']);

        $this->assertSame([['/a/', '/b/']], $adaptor->purgedUrls);
    }

    public function testAFailedPurgeIsLogged(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->nextResult = PurgeResult::failure('AccessDenied');
        $logger = new RecordingLogger();

        $service = $this->service($adaptor);
        $service->setLogger($logger);

        $result = $service->purgeUrls(['/a/']);

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(1, $logger->errors);
        $this->assertStringContainsString('AccessDenied', $logger->errors[0]);
    }

    public function testAProviderThatThrowsBecomesAFailedResultAndALogLine(): void
    {
        $adaptor = new FakePurgeAdaptor();
        $adaptor->throwMessage = 'Connection refused';
        $logger = new RecordingLogger();

        $service = $this->service($adaptor);
        $service->setLogger($logger);

        $result = $service->purgeAll();

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Connection refused', $result->getMessage());
        $this->assertCount(1, $logger->errors);
    }
}
