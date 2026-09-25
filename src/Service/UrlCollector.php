<?php

namespace Blueo\Purge\Service;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * The URLs that go stale when one page changes.
 *
 * A page is not the only thing that changes when it is published. The listing
 * it sits under shows its title, and the home page often shows the latest of
 * something. Those pages are not republished, so nothing else would purge
 * them.
 *
 * The rules here are the three that apply to most sites: the page, its parent,
 * and the home page. Each is a config flag, and extra_urls adds fixed paths
 * such as a sitemap or a JSON feed. A site whose staleness does not follow the
 * tree replaces this class through Injector rather than configuring a rule for
 * every case.
 *
 * The owner is read through method_exists and not through a type, because this
 * module requires silverstripe/framework alone. A page is whatever object has
 * a Link().
 */
class UrlCollector
{
    use Injectable;
    use Configurable;

    /**
     * The page that changed.
     */
    private static bool $include_self = true;

    /**
     * The listing the page sits under. It shows the page title and the page
     * order, and neither is republished when a child changes.
     */
    private static bool $include_parent = true;

    /**
     * The home page. Sites put a latest-items list there.
     */
    private static bool $include_home = true;

    /**
     * Paths purged whenever any page changes, for example '/sitemap.xml'.
     */
    private static array $extra_urls = [];

    /**
     * @return array<string> Site relative paths, each with a leading slash.
     */
    public function collect(object $page): array
    {
        $urls = [];

        if ($this->config()->get('include_self')) {
            $urls[] = $this->linkFor($page);
        }

        if ($this->config()->get('include_parent')) {
            $urls[] = $this->linkFor($this->parentOf($page));
        }

        if ($this->config()->get('include_home')) {
            $urls[] = '/';
        }

        foreach ((array) $this->config()->get('extra_urls') as $extra) {
            $urls[] = $this->normalise((string) $extra);
        }

        return $this->clean($urls);
    }

    /**
     * Turn any of a path, a site relative link or an absolute URL into a path
     * with a leading slash. A query string is kept, because a cache keys on it.
     */
    public function normalise(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $path = parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            $url = ($path === null || $path === '' ? '/' : $path) . ($query ? '?' . $query : '');
        }

        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        return $url;
    }

    private function linkFor(?object $page): string
    {
        if ($page === null || !method_exists($page, 'Link')) {
            return '';
        }

        return $this->normalise((string) $page->Link());
    }

    private function parentOf(object $page): ?object
    {
        if (!method_exists($page, 'Parent')) {
            return null;
        }

        $parent = $page->Parent();

        // A page at the top of the tree returns an empty record rather than
        // null, and an empty record has no link worth purging.
        if (!is_object($parent) || !($parent->ID ?? 0)) {
            return null;
        }

        return $parent;
    }

    /**
     * @param array<string> $urls
     * @return array<string>
     */
    private function clean(array $urls): array
    {
        $urls = array_filter($urls, fn (string $url): bool => $url !== '');

        return array_values(array_unique($urls));
    }
}
