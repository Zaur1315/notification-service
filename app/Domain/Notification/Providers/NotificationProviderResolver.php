<?php

declare(strict_types=1);

namespace App\Domain\Notification\Providers;

use App\Domain\Notification\Contracts\NotificationProviderInterface;
use App\Domain\Notification\Enums\NotificationChannel;

/**
 * Resolves the correct notification provider for the selected channel.
 *
 * Delivery services depend on this resolver instead of concrete providers,
 * which keeps provider selection centralized and easy to extend.
 */
final readonly class NotificationProviderResolver
{
    public function __construct(
        private SmsProviderMock   $smsProvider,
        private EmailProviderMock $emailProvider,
    )
    {
    }

    public function resolve(NotificationChannel $channel): NotificationProviderInterface
    {
        return match ($channel) {
            NotificationChannel::Sms => $this->smsProvider,
            NotificationChannel::Email => $this->emailProvider,
        };
    }
}
