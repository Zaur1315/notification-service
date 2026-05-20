<?php

declare(strict_types=1);

namespace App\Domain\Notification\DTO;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;

/**
 * Immutable input DTO for notification creation.
 *
 * The DTO isolates validated transport data from HTTP layer implementation
 * and provides a stable structure for domain services.
 */
final readonly class StoreNotificationData
{
    public function __construct(
        public NotificationChannel  $channel,
        public NotificationPriority $priority,
        public string               $message,
        public array                $recipientIds,
        public ?string              $idempotencyKey,
    )
    {
    }
}
