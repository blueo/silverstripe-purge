<?php

namespace Blueo\Purge\Tests;

use Blueo\Purge\Service\UrlCollector;
use SilverStripe\Dev\SapphireTest;

class UrlCollectorTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function page(): FakePage
    {
        $home = new FakePage(1, '/');
        $news = new FakePage(2, '/news/', $home);

        return new FakePage(3, '/news/budget-2026/', $news);
    }

    public function testThePageItsParentAndTheHomePageAreCollected(): void
    {
        $urls = UrlCollector::create()->collect($this->page());

        $this->assertSame(['/news/budget-2026/', '/news/', '/'], $urls);
    }

    public function testAPageAtTheTopOfTheTreeCollectsItselfAndHome(): void
    {
        $urls = UrlCollector::create()->collect(new FakePage(2, '/news/'));

        $this->assertSame(['/news/', '/'], $urls);
    }

    public function testTheHomePageIsNotCollectedTwice(): void
    {
        $urls = UrlCollector::create()->collect(new FakePage(1, '/'));

        $this->assertSame(['/'], $urls);
    }

    public function testTheParentCanBeTurnedOff(): void
    {
        UrlCollector::config()->set('include_parent', false);

        $this->assertSame(['/news/budget-2026/', '/'], UrlCollector::create()->collect($this->page()));
    }

    public function testTheHomePageCanBeTurnedOff(): void
    {
        UrlCollector::config()->set('include_home', false);

        $this->assertSame(['/news/budget-2026/', '/news/'], UrlCollector::create()->collect($this->page()));
    }

    public function testThePageItselfCanBeTurnedOff(): void
    {
        UrlCollector::config()->set('include_self', false);

        $this->assertSame(['/news/', '/'], UrlCollector::create()->collect($this->page()));
    }

    public function testExtraUrlsAreAppended(): void
    {
        UrlCollector::config()->set('extra_urls', ['/sitemap.xml', 'feed.json']);

        $this->assertSame(
            ['/news/budget-2026/', '/news/', '/', '/sitemap.xml', '/feed.json'],
            UrlCollector::create()->collect($this->page())
        );
    }

    public function testAnObjectWithoutALinkCollectsNothingOfItsOwn(): void
    {
        UrlCollector::config()->set('include_home', false);

        $this->assertSame([], UrlCollector::create()->collect(new \stdClass()));
    }

    public function testAnAbsoluteLinkIsReducedToAPath(): void
    {
        $collector = UrlCollector::create();

        $this->assertSame('/news/', $collector->normalise('https://www.example.com/news/'));
        $this->assertSame('/', $collector->normalise('https://www.example.com'));
    }

    public function testAQueryStringIsKept(): void
    {
        $this->assertSame(
            '/search/?q=budget',
            UrlCollector::create()->normalise('https://www.example.com/search/?q=budget')
        );
    }

    public function testALeadingSlashIsAdded(): void
    {
        $this->assertSame('/news/', UrlCollector::create()->normalise('news/'));
    }

    public function testAnEmptyStringStaysEmpty(): void
    {
        $this->assertSame('', UrlCollector::create()->normalise('   '));
    }
}
