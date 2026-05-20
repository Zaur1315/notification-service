<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\DTO\StoreNotificationData;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Infrastructure\RabbitMQ\NotificationQueuePublisher;
use App\Models\Notification\Notification;
use Illuminate\Support\Facades\DB;

final class NotificationCreationService
{
    public function __construct(
        private readonly NotificationQueuePublisher $queuePublisher,
    ) {
    }

    public function create(StoreNotificationData $data): Notification
    {
        $notification = DB::transaction(function () use ($data): Notification {
            $notification = Notification::query()->create([
                'channel' => $data->channel,
                'priority' => $data->priority,
                'message' => $data->message,
                'idempotency_key' => $data->idempotencyKey,
            ]);

            /*
             * Recipients are inserted in bulk to keep mass notification creation fast.
             * Each recipient starts from the queued status and will be processed later
             * by the asynchronous worker.
             */
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

        /*
         * Publishing happens after database commit.
         * This prevents workers from receiving a message before the recipient row
         * becomes durable in PostgreSQL.
         */
        foreach ($notification->recipients as $recipient) {
            $this->queuePublisher->publishRecipient(
                notificationRecipientId: $recipient->id,
                priority: $data->priority,
            );
        }

        return $notification;
    }
}
