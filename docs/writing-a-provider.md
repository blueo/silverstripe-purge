# Writing a provider

A provider module binds an implementation of `Blueo\Purge\Service\PurgeAdaptor`
over the interface through Injector. This module then does the triggering, the
timing, the capability refusal and the logging.

## The interface

```php
namespace Blueo\Purge\Service;

interface PurgeAdaptor
{
    public function supports(PurgeCapability $capability): bool;

    public function purgeUrls(array $urls): PurgeResult;

    public function purgeAll(): PurgeResult;

    public function purgeTags(array $tags): PurgeResult;
}
```

Four rules:

1. `supports()` answers without a network call. A caller asks it before every
   operation, and a task prints the answers.
2. A method whose capability you do not declare throws
   `UnsupportedOperationException`. It does not return a failed result. A
   result records an attempt, and no attempt was made.
3. A provider error becomes a failed `PurgeResult` and not an exception.
   `PurgeService` logs it. An author publishing a page must not see a CDN
   outage as a 500.
4. `purgeUrls()` receives paths with a leading slash, or absolute URLs on the
   site. Normalise them to what your API takes.

## The result

```php
PurgeResult::success($providerReference, $message, $pathCount);
PurgeResult::failure($message, $providerReference, $pathCount);
PurgeResult::combine([$first, $second]);
```

Put the provider's own identifier in the reference where there is one. It is
what an operator types into the provider's console to look the purge up.
CloudFront returns an invalidation id. A provider with no such identifier
leaves it null.

`combine()` folds the results of several API calls into one result, because
the caller asked for one purge. It fails if any call failed, because a partial
purge leaves stale pages.

## The config block

Follow the gate. The Injector block activates only when the environment
variable that configures the provider is set, so installing the module changes
nothing until it is configured:

```yaml
---
Name: blueo-purge-myprovider
After:
  - '#blueo-purge'
Only:
  envvarset: 'PURGE_MYPROVIDER_API_KEY'
---
SilverStripe\Core\Injector\Injector:
  Blueo\Purge\Service\PurgeAdaptor:
    class: Blueo\Purge\MyProvider\Service\MyProviderPurgeAdaptor
  MyProvider\Sdk\Client.purgeClient:
    factory: Blueo\Purge\MyProvider\Service\ClientFactory
    constructor:
      api_key: '`PURGE_MYPROVIDER_API_KEY`'
```

`After: '#blueo-purge'` puts the block after the core block, which binds
`NullPurgeAdaptor`.

Build the SDK client in a `Factory` and not in the adaptor. A factory keeps
the environment reading in one class, and a test builds the client itself.

## Batching

Providers cap the work in one call, charge for each item, or both. Batch in
the provider, where the numbers are known, and return one combined result. The
CloudFront provider has a `PathBatcher` that normalises, removes repeats and
cuts the list into requests.

## Tests to write

* Every capability your provider declares, and every one it does not.
* The undeclared capability throws rather than returns.
* Batching, at the boundary and one past it.
* A provider error becomes a failed result and not an exception.
