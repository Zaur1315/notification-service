<?php

declare(strict_types=1);

namespace App\Domain\Notification\Providers;

use App\Domain\Notification\Contracts\NotificationProviderInterface;
use App\Domain\Notification\DTO\ProviderSendResult;
use App\Models\Notification\NotificationRecipient;

/**
 * Mock SMS provider used for local development and integration tests.
 *
 * The class simulates an external SMS gateway without sending real messages.
 * Subscriber ID 999 intentionally returns a temporary failure to verify retry
 * and dropped delivery flows.
 */
final class SmsProviderMock implements NotificationProviderInterface
{
    public function send(NotificationRecipient $recipient): ProviderSendResult
    {
        $recipientId = (int)$recipient->getKey();
        $subscriberId = (int)$recipient->getAttribute('subscriber_id');

        if ($subscriberId === 999) {
            return new ProviderSendResult(
                success: false,
                errorMessage: 'Temporary SMS provider failure.',
                temporaryFailure: true,
            );
        }

        return new ProviderSendResult(
            success: true,
            providerMessageId: sprintf('sms_mock_%d_%s', $recipientId, uniqid()),
        );
    }

    public function name(): string
    {
        return 'sms_mock';
    }
}
