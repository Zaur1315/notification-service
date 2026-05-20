<?php

declare(strict_types=1);

namespace App\Domain\Notification\Providers;

use App\Domain\Notification\Contracts\NotificationProviderInterface;
use App\Domain\Notification\DTO\ProviderSendResult;
use App\Models\Notification\NotificationRecipient;

final class SmsProviderMock implements NotificationProviderInterface
{
    public function send(NotificationRecipient $recipient): ProviderSendResult
    {
        if ((int) $recipient->getAttribute('subscriber_id') === 999) {
            return new ProviderSendResult(
                success: false,
                errorMessage: 'Temporary SMS provider failure.',
                temporaryFailure: true,
            );
        }

        return new ProviderSendResult(
            success: true,
            providerMessageId: sprintf('sms_mock_%d_%s', $recipient->getKey(), uniqid()),
        );
    }

    public function name(): string
    {
        return 'sms_mock';
    }
}
