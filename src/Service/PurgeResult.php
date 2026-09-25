<?php

namespace Blueo\Purge\Service;

use Stringable;

/**
 * What one purge call did.
 *
 * The reference is the provider's own identifier for the work, where the
 * provider gives one. CloudFront returns an invalidation id, which is the
 * value an operator needs to look the purge up in the AWS console. A provider
 * without such an identifier leaves it null.
 *
 * The count is the number of items submitted, which for CloudFront is the
 * number of paths. Paths over the free monthly allowance are charged, so the
 * count is the figure a site reads to know what it spends.
 */
final class PurgeResult implements Stringable
{
    private function __construct(
        private readonly bool $successful,
        private readonly ?string $reference,
        private readonly string $message,
        private readonly int $count,
    ) {
    }

    public static function success(?string $reference = null, string $message = '', int $count = 0): self
    {
        return new self(true, $reference, $message, $count);
    }

    public static function failure(string $message, ?string $reference = null, int $count = 0): self
    {
        return new self(false, $reference, $message, $count);
    }

    /**
     * Fold the results of several calls into one.
     *
     * A purge of more than the provider's batch size is several calls. The
     * caller asked for one purge, so it reads one result. The combination
     * fails if any call failed, because a partial purge leaves stale content.
     *
     * @param array<PurgeResult> $results
     */
    public static function combine(array $results): self
    {
        if (!$results) {
            return self::success(null, 'Nothing to purge.');
        }

        $successful = true;
        $references = [];
        $messages = [];
        $count = 0;

        foreach ($results as $result) {
            $successful = $successful && $result->isSuccessful();
            $count += $result->getCount();

            if ($result->getReference() !== null) {
                $references[] = $result->getReference();
            }

            if ($result->getMessage() !== '') {
                $messages[] = $result->getMessage();
            }
        }

        $reference = $references ? implode(', ', $references) : null;

        return new self($successful, $reference, implode(' ', $messages), $count);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function __toString(): string
    {
        $parts = [$this->successful ? 'Purged' : 'Purge failed'];
        $parts[] = sprintf('%d item(s).', $this->count);

        if ($this->reference !== null) {
            $parts[] = sprintf('Reference %s.', $this->reference);
        }

        if ($this->message !== '') {
            $parts[] = $this->message;
        }

        return implode(' ', $parts);
    }
}
