<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exceptions;

use RuntimeException;

/**
 * Signals that notification delivery failed due to a temporary provider issue.
 *
 * Such exceptions are considered retryable by queue consumers.
 * The message may be requeued until the configured retry limit is reached.
 */
final class TemporaryProviderFailureException extends RuntimeException
{
}
