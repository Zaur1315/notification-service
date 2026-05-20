<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\DTO\ProviderSendResult;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\TemporaryProviderFailureException;
use App\Domain\Notification\Providers\NotificationProviderResolver;
use App\Models\Notification\DeliveryAttempt;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NotificationDeliveryService
{
    public function __construct(
        private readonly NotificationProviderResolver $providerResolver,
    ) {
    }

    public function send(int $notificationRecipientId): void
    {
        $recipient = NotificationRecipient::query()
            ->with('notification')
            ->find($notificationRecipientId);

        if (!$recipient instanceof NotificationRecipient) {
            throw new RuntimeException(
                sprintf(
                    'Notification recipient "%d" was not found.',
                    $notificationRecipientId
                )
            );
        }

        if ($recipient->status !== NotificationStatus::Queued) {
            return;
        }

        $provider = $this->providerResolver->resolve($recipient->notification->channel);
        $result = $provider->send($recipient);

        $this->storeDeliveryResult(
            recipient: $recipient,
            providerName: $provider->name(),
            result: $result,
        );

        if (!$result->success && $result->temporaryFailure) {
            throw new TemporaryProviderFailureException(
                $result->errorMessage ?? 'Temporary provider failure.'
            );
        }
    }

    private function storeDeliveryResult(
        NotificationRecipient $recipient,
        string $providerName,
        ProviderSendResult $result,
    ): void {
        DB::transaction(function () use ($recipient, $providerName, $result): void {
            $lockedRecipient = NotificationRecipient::query()
                ->lockForUpdate()
                ->find($recipient->id);

            if (!$lockedRecipient instanceof NotificationRecipient) {
                throw new RuntimeException('Notification recipient was not found during status update.');
            }

            if ($lockedRecipient->status !== NotificationStatus::Queued) {
                return;
            }

            $attemptNumber = DeliveryAttempt::query()
                    ->where('notification_recipient_id', $lockedRecipient->id)
                    ->count() + 1;

            DeliveryAttempt::query()->create([
                'notification_recipient_id' => $lockedRecipient->id,
                'attempt_number' => $attemptNumber,
                'provider' => $providerName,
                'status' => $result->success ? NotificationStatus::Sent->value : NotificationStatus::Dropped->value,
                'error_message' => $result->errorMessage,
                'attempted_at' => now(),
            ]);

            if (!$result->success) {
                return;
            }

            $lockedRecipient->update([
                'status' => NotificationStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'sent_at' => now(),
            ]);
        });
    }
}
