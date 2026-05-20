<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

/**
 * Notification priority affects queue routing and delivery urgency.
 *
 * Transactional notifications are routed to the high-priority queue,
 * while marketing notifications are processed through the default queue.
 */
enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    /**
     * Returns scalar enum values for validation rules and filters.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
