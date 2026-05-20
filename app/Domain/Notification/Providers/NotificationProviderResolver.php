<?php

declare(strict_types=1);

namespace App\Domain\Notification\Providers;

use App\Domain\Notification\Contracts\NotificationProviderInterface;
use App\Domain\Notification\Enums\NotificationChannel;
use InvalidArgumentException;

final class NotificationProviderResolver
{
    public function __construct(
        private readonly SmsProviderMock $smsProvider,
        private readonly EmailProviderMock $emailProvider,
    ) {
    }

    public function resolve(NotificationChannel $channel): NotificationProviderInterface
    {
        return match ($channel) {
            NotificationChannel::Sms => $this->smsProvider,
            NotificationChannel::Email => $this->emailProvider,
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unsupported notification channel "%s".',
                    $channel->value
                )
            ),
        };
    }
}
