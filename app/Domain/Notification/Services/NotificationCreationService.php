<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\DTO\StoreNotificationData;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Infrastructure\RabbitMQ\NotificationQueuePublisher;
use App\Models\Notification\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates notification batches and schedules every recipient for async delivery.
 *
 * The service is responsible for:
 * - idempotency lookup;
 * - durable database persistence;
 * - recipient bulk creation;
 * - queue publishing after successful database commit.
 */
final class NotificationCreationService
{
    private const POSTGRES_UNIQUE_VIOLATION_CODE = '23505';

    public function __construct(
        private readonly NotificationQueuePublisher     $queuePublisher,
        private readonly NotificationIdempotencyService $idempotencyService,
    )
    {
    }

    /**
     * @throws \JsonException
     */
    public function create(StoreNotificationData $data): Notification
    {
        $existingNotification = $this->findExistingNotification($data);

        if ($existingNotification instanceof Notification) {
            return $existingNotification;
        }

        $notification = $this->createNotificationWithRecipients($data);

        $this->rememberIdempotencyKey($data, $notification);

        $this->publishRecipients($notification, $data);

        return $notification;
    }

    private function findExistingNotification(StoreNotificationData $data): ?Notification
    {
        $existingNotificationId = $this->idempotencyService->findExistingNotificationId($data->idempotencyKey);

        if ($existingNotificationId === null) {
            return null;
        }

        return Notification::query()
            ->with('recipients')
            ->find($existingNotificationId);
    }

    private function createNotificationWithRecipients(StoreNotificationData $data): Notification
    {
        try {
            return DB::transaction(function () use ($data): Notification {
                $notification = Notification::query()->create([
                    'channel' => $data->channel,
                    'priority' => $data->priority,
                    'message' => $data->message,
                    'idempotency_key' => $data->idempotencyKey,
                ]);

                $now = now();

                /*
                 * Recipients are inserted in bulk because a notification may target
                 * thousands of subscribers. Each recipient starts in queued status
                 * and is processed later by RabbitMQ consumers.
                 */
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
            return $this->recoverFromDuplicateIdempotencyKey($exception, $data);
        }
    }

    private function recoverFromDuplicateIdempotencyKey(
        QueryException        $exception,
        StoreNotificationData $data,
    ): Notification
    {
        /*
         * PostgreSQL unique index is the final protection layer against races:
         * two concurrent requests may pass Redis lookup before either key is saved.
         * In that case one insert wins, the other receives unique violation and
         * returns the already-created notification instead of failing with 500.
         */
        if (
            $exception->getCode() === self::POSTGRES_UNIQUE_VIOLATION_CODE
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

    private function rememberIdempotencyKey(
        StoreNotificationData $data,
        Notification          $notification,
    ): void
    {
        if ($data->idempotencyKey === null || trim($data->idempotencyKey) === '') {
            return;
        }

        $this->idempotencyService->remember($data->idempotencyKey, $notification);
    }

    /**
     * @throws \JsonException
     */
    private function publishRecipients(Notification $notification, StoreNotificationData $data): void
    {
        /*
         * Publishing is intentionally performed after the database transaction.
         * This prevents consumers from receiving messages for recipients that are
         * not yet durably stored in PostgreSQL.
         */
        foreach ($notification->recipients as $recipient) {
            $this->queuePublisher->publishRecipient(
                notificationRecipientId: (int)$recipient->id,
                priority: $data->priority,
            );
        }
    }
}
