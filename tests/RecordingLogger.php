<?php

namespace Blueo\Purge\Tests;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps the error lines a test asserts on.
 */
class RecordingLogger extends AbstractLogger
{
    public array $errors = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ((string) $level === 'error') {
            $this->errors[] = (string) $message;
        }
    }
}
