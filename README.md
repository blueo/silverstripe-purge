# blueo/silverstripe-purge

Purging of a cache in front of a Silverstripe CMS 6 site, through interfaces
that do not change when the CDN changes.

**This module purges nothing on its own.** It holds the interfaces, the
triggers and the timing. A provider module binds an implementation over the
adaptor interface. Without one, every purge call throws and names the missing
provider.

* [Purpose](#purpose)
* [Installation](#installation)
* [Feature support](#feature-support)
* [Capabilities](#capabilities)
* [What gets purged, and when](#what-gets-purged-and-when)
* [Timing](#timing)
* [The task](#the-task)
* [Logging](#logging)
* [Configuration](#configuration)
* [Available provider modules](#available-provider-modules)
* [Writing a provider](docs/writing-a-provider.md)
* [Triggers in detail](docs/triggers.md)

## Purpose

A site in front of a CDN has to tell the CDN when a page changed. The API that
does this differs between CloudFront, Fastly, Akamai and Cloudflare, and a site
that calls one of them by name is tied to it.

This module gives a site three calls that stay the same across providers:
purge these URLs, purge everything, purge these tags. A change of CDN is a
change of provider module and an environment variable.

## Installation

```bash
composer require blueo/silverstripe-purge
composer require blueo/silverstripe-purge-cloudfront
sake dev/build flush=1
```

Install a provider at the same time. See
[available provider modules](#available-provider-modules).

## Feature support

What **this module** supports, against what a CDN might offer. A provider
declares which of these it performs, and this module refuses the rest. See
[capabilities](#capabilities).

| Feature | Module support | Rationale |
|---|---|---|
| Purge by URL | Yes | Every CDN has it. It is the operation a publish uses. |
| Purge everything | Yes | Every CDN has it. It is what a template change needs. |
| Purge by tag (surrogate key) | Yes, where the provider has it | A tag purge removes every page that carries a key, without listing the pages. The CloudFront provider declares this capability false and a call throws, because a CloudFront tag invalidation is accepted by a distribution that is not configured for tags and removes nothing. |
| Purge on publish, unpublish and delete | Yes | Applied to `SiteTree` when silverstripe/cms is installed. |
| Purge from a queue | Yes, where silverstripe/queuedjobs is installed | A publish must not wait on a CDN round trip. |
| Soft purge, stale while revalidate | No | Fastly and Cloudflare express it, CloudFront does not. A capability that two of four providers offer moves a site's behaviour when the CDN changes. |
| Purge by prefix or by wildcard | No | Pass a provider's own wildcard as a URL where the provider accepts one, for example `/news/*` on CloudFront. The syntax is the provider's, so this module does not name it as a feature. |
| Read the status of a purge | No | The result carries the provider reference. Look the purge up with it in the provider's console. |

## Capabilities

No CDN performs every operation. The interface carries all three, so a site
calls the same method whichever provider is bound, and each provider declares
what it does:

```php
use Blueo\Purge\Service\PurgeCapability;
use Blueo\Purge\Service\PurgeService;

$service = PurgeService::singleton();

if ($service->supports(PurgeCapability::PurgeTags)) {
    $service->purgeTags(['news']);
}
```

Three rules hold:

1. The answer is available before the call. `supports()` and
   `getCapabilities()` take no network call and do no work.
2. An undeclared operation throws `UnsupportedOperationException`. It does not
   return a result, and it does not return quietly. A site that reads a
   success for work nobody did believes its pages are fresh.
3. The refusal is in `PurgeService`, not in the provider. A provider that
   forgets to check still cannot run an operation it did not declare.

`PurgeCapability` is an enum rather than one interface for each operation. The
cases can be listed, so `sake tasks:blueo-purge --capabilities` prints the
support matrix of the installed provider on the running site. Separate
interfaces would need an `instanceof` at every call site and could not be
printed.

## What gets purged, and when

A publish changes more than the page. The listing above it shows the page
title and the page order. The home page often shows the latest of something.
Neither is republished, so nothing else purges them.

`UrlCollector` returns these for a changed page:

| URL | Config flag | Default |
|---|---|---|
| The page | `include_self` | On |
| The parent page | `include_parent` | On |
| The home page | `include_home` | On |
| Fixed paths, for example `/sitemap.xml` | `extra_urls` | Empty |

`PurgeOnPublishExtension` collects them on `onAfterPublish`,
`onAfterUnpublish` and `onAfterDelete`, and hands them to `PurgeDispatcher`.

This is three rules and a list, and not a rules engine. A site whose staleness
does not follow the tree replaces `UrlCollector` through Injector, and gets one
class to write rather than a configuration language to learn. See
[triggers in detail](docs/triggers.md), which also lists what these rules do
not cover.

## Timing

A purge is an HTTP call to a CDN, and an author who publishes a page waits for
the response.

* With silverstripe/queuedjobs installed, the purge goes to `PurgeJob` and the
  publish returns.
* Without it, the purge is sent in the same request.

queuedjobs is a suggestion in `composer.json` and never a requirement. The
class is reached by name and never imported, and `src/Job/` carries a
`_manifest_exclude` file so a site without queuedjobs never loads the job.

`PurgeDispatcher::canQueue()` reports which of the two is in use.

## The task

```bash
sake tasks:blueo-purge --capabilities
sake tasks:blueo-purge --url=/about-us/ --url=/news/
sake tasks:blueo-purge --all
sake tasks:blueo-purge --url=/news/ --queue
```

`--capabilities` prints the bound provider, what it supports and whether
purges are queued, and purges nothing. Run it first on a new environment.

## Logging

A failed purge writes an error through `Psr\Log\LoggerInterface`, which on a
Silverstripe site reaches the error handler and whatever it is configured to
send to. Three paths lead there:

* A provider returns a failed result. `PurgeService` logs the message.
* A provider throws. `PurgeService` logs the exception and returns a failed
  result, so one CDN outage does not stop authors publishing.
* A trigger fails. `PurgeOnPublishExtension` logs the class and the ID of the
  page, so the log line names the page that is now stale.

An operation the provider does not support is not logged. It throws, because
it is a mistake in the calling code and not a fault at run time.

## Configuration

```yaml
Blueo\Purge\Service\UrlCollector:
  include_parent: true
  include_home: true
  extra_urls:
    - '/sitemap.xml'

Blueo\Purge\Service\PurgeDispatcher:
  # Send every purge inline, including on a site that has queuedjobs.
  use_queue: true

Blueo\Purge\Extension\PurgeOnPublishExtension:
  # Turn the publish triggers off and purge from the task or your own code.
  purge_on_change: true
```

## Available provider modules

| Module | Purge by URL | Purge everything | Purge by tag |
|---|---|---|---|
| [blueo/silverstripe-purge-cloudfront](https://github.com/blueo/silverstripe-purge-cloudfront) | Yes | Yes | No |
| None installed (`NullPurgeAdaptor`) | Throws | Throws | Throws |

## Tests

```bash
composer install
vendor/bin/phpunit
```

The tests run from the module directory against SQLite. There is no site to
stand up first.
