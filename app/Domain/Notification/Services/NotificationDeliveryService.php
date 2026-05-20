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

/**
 * Delivers one queued notification recipient through the resolved provider.
 *
 * The service is intentionally focused on a single recipient because queue
 * messages contain recipient-level jobs. This makes retries, status transitions
 * and delivery attempts independent for every subscriber.
 */
final readonly class NotificationDeliveryService
{
    public function __construct(
        private NotificationProviderResolver $providerResolver,
    )
    {
    }

    public function send(int $notificationRecipientId): void
    {
        $recipient = $this->findRecipient($notificationRecipientId);

        /*
         * Business-level exactly-once guard.
         * RabbitMQ provides at-least-once delivery, so the same message may be
         * consumed more than once. Already processed recipients must not be sent again.
         */
        if ($recipient->status !== NotificationStatus::Queued) {
            return;
        }

        $provider = $this->providerResolver->resolve($recipient->notification->channel);
        $result = $provider->send($recipient);

        /*
         * Delivery result is stored before throwing retryable exception.
         * Otherwise failed attempts would be rolled back and retry counters would
         * never reach the configured limit.
         */
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

    private function findRecipient(int $notificationRecipientId): NotificationRecipient
    {
        $recipient = NotificationRecipient::query()
            ->with('notification')
            ->find($notificationRecipientId);

        if (!$recipient instanceof NotificationRecipient) {
            throw new RuntimeException(sprintf(
                'Notification recipient "%d" was not found.',
                $notificationRecipientId
            ));
        }

        return $recipient;
    }

    private function storeDeliveryResult(
        NotificationRecipient $recipient,
        string                $providerName,
        ProviderSendResult    $result,
    ): void
    {
        DB::transaction(function () use ($recipient, $providerName, $result): void {
            $lockedRecipient = NotificationRecipient::query()
                ->lockForUpdate()
                ->find($recipient->id);

            if (!$lockedRecipient instanceof NotificationRecipient) {
                throw new RuntimeException('Notification recipient was not found during status update.');
            }

            /*
             * The row is locked before updating to prevent concurrent workers from
             * writing conflicting statuses for the same recipient.
             */
            if ($lockedRecipient->status !== NotificationStatus::Queued) {
                return;
            }

            $attemptNumber = $this->nextAttemptNumber($lockedRecipient);

            DeliveryAttempt::query()->create([
                'notification_recipient_id' => $lockedRecipient->id,
                'attempt_number' => $attemptNumber,
                'provider' => $providerName,
                'status' => $result->success
                    ? NotificationStatus::Sent->value
                    : NotificationStatus::Dropped->value,
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

    private function nextAttemptNumber(NotificationRecipient $recipient): int
    {
        return DeliveryAttempt::query()
                ->where('notification_recipient_id', $recipient->id)
                ->count() + 1;
    }
}
