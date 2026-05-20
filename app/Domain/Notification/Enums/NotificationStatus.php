<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

/**
 * Represents the current delivery lifecycle state of a notification recipient.
 *
 * Statuses are updated asynchronously by queue consumers and delivery providers.
 */
enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Dropped = 'dropped';

    /**
     * Returns scalar enum values for validation rules and filters.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
