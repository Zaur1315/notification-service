<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

/**
 * Supported notification transport channels.
 *
 * The channel determines:
 * - which provider will be used;
 * - which queue routing rules are applied;
 * - how notifications are processed downstream.
 */
enum NotificationChannel: string
{
    case Sms = 'sms';
    case Email = 'email';

    /**
     * Returns scalar enum values for validation rules and filters.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
