# Triggers

What the publish triggers purge, why those URLs, and what they do not cover.

## The hooks

`Blueo\Purge\Extension\PurgeOnPublishExtension` is applied to
`SilverStripe\CMS\Model\SiteTree` by `_config/purge.yml`. That config block
carries `Only: moduleexists: 'silverstripe/cms'`, because this module requires
silverstripe/framework alone and `SiteTree` belongs to silverstripe/cms.

| Hook | When |
|---|---|
| `onAfterPublish` | A page is published, including a publish of a parent that cascades |
| `onAfterUnpublish` | A page is removed from the live stage |
| `onAfterDelete` | A page record is deleted |

Each hook catches every exception and writes it to the log. A CDN that is down
does not stop an author publishing.

## The URLs

`Blueo\Purge\Service\UrlCollector` reads the changed record and returns site
relative paths.

```php
// A page at /news/budget-2026/ under /news/ returns:
['/news/budget-2026/', '/news/', '/']
```

The page itself is the obvious one. The other two are there because nothing
else purges them:

* **The parent.** A listing page shows the titles of its children and their
  order. Publishing a child changes both, and the parent is not republished.
* **The home page.** Sites put a list of the latest items on it, drawn from
  pages that are published elsewhere in the tree.

A duplicate is dropped. Publishing the home page returns `['/']` once.

`extra_urls` adds fixed paths that change whenever any page changes, for
example a sitemap or a JSON feed read by another system.

## What these rules do not cover

Four cases produce a stale page that the triggers do not purge. Each has a
route, and none of them is the default:

1. **A changed `URLSegment`.** The page moves to a new path, and the old path
   stays in the CDN until it expires. The old path is on the live record
   before the publish, and reading it needs silverstripe/versioned, which this
   module does not require. Purge by hand, or purge everything, after a bulk
   rename.
2. **A page listed somewhere other than its parent or the home page.** A tag
   page, a search results page, a related-items block on a sibling. Add the
   fixed ones to `extra_urls`. For the rest, replace `UrlCollector`.
3. **A `DataObject` that is not a page but appears on one.** A staff record in
   a listing, for example. Add your own extension that calls
   `PurgeDispatcher::singleton()->purgeUrls()` with the pages that show it.
4. **A template or config change.** No page is published, so no trigger runs.
   Run `sake tasks:blueo-purge --all` from the deployment.

## Replacing the collector

The collector is one class with one method that matters. A site whose
staleness does not follow the page tree writes its own:

```php
namespace App\Purge;

use Blueo\Purge\Service\UrlCollector;

class SiteUrlCollector extends UrlCollector
{
    public function collect(object $page): array
    {
        $urls = parent::collect($page);

        if ($page instanceof NewsPage) {
            $urls[] = '/news/archive/';
            $urls[] = '/api/news.json';
        }

        return array_values(array_unique($urls));
    }
}
```

```yaml
SilverStripe\Core\Injector\Injector:
  Blueo\Purge\Service\UrlCollector:
    class: App\Purge\SiteUrlCollector
```

The collector reads the page through `method_exists` and not through a type,
so it takes any object with a `Link()`. This is what lets the module require
silverstripe/framework alone, and it is what lets the tests run without
silverstripe/cms installed.

## Cost

Each URL collected is one path sent to the provider. CloudFront charges for
each path over 1,000 in a month, across the AWS account. Three URLs for each
publish is the default because the default has to be correct. A site that
publishes often and watches the bill turns `include_home` off, or turns the
triggers off and purges everything once at the end of a deployment.
