<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Enums\NotificationStatus;
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
        DB::transaction(function () use ($notificationRecipientId): void {
            $recipient = NotificationRecipient::query()
                ->with('notification')
                ->lockForUpdate()
                ->find($notificationRecipientId);

            if (!$recipient instanceof NotificationRecipient) {
                throw new RuntimeException(
                    sprintf(
                        'Notification recipient "%d" was not found.',
                        $notificationRecipientId
                    )
                );
            }

            /*
             * Business-level exactly-once guard.
             * RabbitMQ provides at-least-once delivery, therefore the same message
             * can theoretically be consumed more than once. We only send recipients
             * that are still in queued status.
             */
            if ($recipient->status !== NotificationStatus::Queued) {
                return;
            }

            $provider = $this->providerResolver->resolve($recipient->notification->channel);
            $result = $provider->send($recipient);

            $attemptNumber = DeliveryAttempt::query()
                    ->where('notification_recipient_id', $recipient->id)
                    ->count() + 1;

            DeliveryAttempt::query()->create([
                'notification_recipient_id' => $recipient->id,
                'attempt_number' => $attemptNumber,
                'provider' => $provider->name(),
                'status' => $result->success ? NotificationStatus::Sent->value : NotificationStatus::Dropped->value,
                'error_message' => $result->errorMessage,
                'attempted_at' => now(),
            ]);

            if (!$result->success) {
                $recipient->update([
                    'status' => NotificationStatus::Dropped,
                    'dropped_at' => now(),
                ]);

                return;
            }

            $recipient->update([
                'status' => NotificationStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'sent_at' => now(),
            ]);
        });
    }
}
