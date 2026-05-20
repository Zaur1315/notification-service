<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\DTO\StoreNotificationData;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Infrastructure\RabbitMQ\NotificationQueuePublisher;
use App\Models\Notification\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class NotificationCreationService
{
    public function __construct(
        private readonly NotificationQueuePublisher $queuePublisher,
        private readonly NotificationIdempotencyService $idempotencyService,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function create(StoreNotificationData $data): Notification
    {
        $existingNotificationId = $this->idempotencyService->findExistingNotificationId($data->idempotencyKey);

        if ($existingNotificationId !== null) {
            return Notification::query()
                ->with('recipients')
                ->findOrFail($existingNotificationId);
        }

        try {
            $notification = DB::transaction(function () use ($data): Notification {
                $notification = Notification::query()->create([
                    'channel' => $data->channel,
                    'priority' => $data->priority,
                    'message' => $data->message,
                    'idempotency_key' => $data->idempotencyKey,
                ]);

                $now = now();

                $recipients = array_map(
                    static fn(int $recipientId): array => [
                        'notification_id' => $notification->id,
                        'subscriber_id' => $recipientId,
                        'status' => NotificationStatus::Queued->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $data->recipientIds
                );

                DB::table('notification_recipients')->insert($recipients);

                return $notification->load('recipients');
            });
        } catch (QueryException $exception) {
            /*
             * PostgreSQL unique violation.
             *
             * Another request already created the notification using the same
             * idempotency key while the current request was in progress.
             *
             * We recover gracefully and return the already existing notification.
             */
            if (
                $exception->getCode() === '23505'
                && $data->idempotencyKey !== null
            ) {
                $existingNotification = Notification::query()
                    ->with('recipients')
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->first();

                if ($existingNotification instanceof Notification) {
                    return $existingNotification;
                }
            }

            throw $exception;
        }

        if ($data->idempotencyKey !== null && trim($data->idempotencyKey) !== '') {
            $this->idempotencyService->remember($data->idempotencyKey, $notification);
        }

        foreach ($notification->recipients as $recipient) {
            $this->queuePublisher->publishRecipient(
                notificationRecipientId: $recipient->id,
                priority: $data->priority,
            );
        }

        return $notification;
    }
}
