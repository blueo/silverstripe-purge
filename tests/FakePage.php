<?php

namespace Blueo\Purge\Tests;

/**
 * Stands in for a SiteTree record.
 *
 * UrlCollector reads a page through method_exists and not through a type, so
 * a plain object with a Link() and a Parent() is enough to exercise it. This
 * module does not require silverstripe/cms, so its tests do not install it.
 */
class FakePage
{
    public int $ID;

    private string $link;

    private ?FakePage $parent;

    public function __construct(int $id, string $link, ?FakePage $parent = null)
    {
        $this->ID = $id;
        $this->link = $link;
        $this->parent = $parent;
    }

    public function Link(): string
    {
        return $this->link;
    }

    public function Parent(): ?FakePage
    {
        return $this->parent;
    }
}
