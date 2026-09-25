<?php

namespace Blueo\Purge\Exception;

use RuntimeException;

/**
 * Thrown when a caller asks a provider for an operation it does not declare.
 *
 * A site that asks for a tag purge on CloudFront gets this exception and not a
 * result. A result would be read as a purge that happened.
 */
class UnsupportedOperationException extends RuntimeException
{
}
