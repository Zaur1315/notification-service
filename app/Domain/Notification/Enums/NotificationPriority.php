<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationPriority: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Dropped = 'dropped';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
