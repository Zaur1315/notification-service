<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
