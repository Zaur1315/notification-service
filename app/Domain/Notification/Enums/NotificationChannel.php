<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationChannel: string
{
    case Sms = 'sms';
    case Email = 'email';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
