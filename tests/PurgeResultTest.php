<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Service\PurgeResult;
use SilverStripe\Dev\SapphireTest;

class PurgeResultTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testASuccessCarriesTheProviderReference(): void
    {
        $result = PurgeResult::success('I2J3K4', 'Submitted.', 12);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('I2J3K4', $result->getReference());
        $this->assertSame('Submitted.', $result->getMessage());
        $this->assertSame(12, $result->getCount());
    }

    public function testAFailureCarriesAMessageAndNoReference(): void
    {
        $result = PurgeResult::failure('AccessDenied');

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getReference());
        $this->assertSame('AccessDenied', $result->getMessage());
    }

    public function testCombinedResultsAddTheirCountsAndKeepEveryReference(): void
    {
        $result = PurgeResult::combine([
            PurgeResult::success('I1', '', 1000),
            PurgeResult::success('I2', '', 40),
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('I1, I2', $result->getReference());
        $this->assertSame(1040, $result->getCount());
    }

    public function testOneFailedCallFailsTheWholePurge(): void
    {
        $result = PurgeResult::combine([
            PurgeResult::success('I1', '', 1000),
            PurgeResult::failure('Throttling', null, 40),
        ]);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('I1', $result->getReference());
        $this->assertStringContainsString('Throttling', $result->getMessage());
    }

    public function testCombiningNothingSucceeds(): void
    {
        $result = PurgeResult::combine([]);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Nothing to purge.', $result->getMessage());
    }

    public function testTheStringFormCarriesTheReferenceForAnOperator(): void
    {
        $this->assertSame(
            'Purged 3 item(s). Reference I2J3K4.',
            (string) PurgeResult::success('I2J3K4', '', 3)
        );
    }
}
