<?php

declare(strict_types=1);

namespace App\Domain\Notification\Providers;

use App\Domain\Notification\Contracts\NotificationProviderInterface;
use App\Domain\Notification\DTO\ProviderSendResult;
use App\Models\Notification\NotificationRecipient;

final class EmailProviderMock implements NotificationProviderInterface
{
    public function send(NotificationRecipient $recipient): ProviderSendResult
    {
        return new ProviderSendResult(
            success: true,
            providerMessageId: sprintf('email_mock_%d_%s', $recipient->id, uniqid()),
        );
    }

    public function name(): string
    {
        return 'email_mock';
    }
}
