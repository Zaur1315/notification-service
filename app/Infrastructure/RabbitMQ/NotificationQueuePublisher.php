<?php

declare(strict_types=1);

namespace App\Infrastructure\RabbitMQ;

use App\Domain\Notification\Enums\NotificationPriority;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Publishes notification recipient jobs to RabbitMQ.
 *
 * Each message contains only the recipient identifier. The consumer loads the
 * fresh database state before delivery, which keeps queue messages small and
 * avoids stale payload problems.
 */
final readonly class NotificationQueuePublisher
{
    public function __construct(
        private RabbitMQConnectionFactory $connectionFactory,
    )
    {
    }

    /**
     * @throws JsonException
     * @throws \Exception
     */
    public function publishRecipient(int $notificationRecipientId, NotificationPriority $priority): void
    {
        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();

        $message = new AMQPMessage(
            body: $this->buildPayload($notificationRecipientId),
            properties: [
                /*
                 * Persistent messages + durable queues allow RabbitMQ to keep
                 * pending notifications after broker restart.
                 */
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ],
        );

        $channel->basic_publish(
            msg: $message,
            exchange: (string)config('rabbitmq.exchange'),
            routing_key: $this->resolveRoutingKey($priority),
        );

        $channel->close();
        $connection->close();
    }

    /**
     * @throws JsonException
     */
    private function buildPayload(int $notificationRecipientId): string
    {
        return json_encode([
            'notification_recipient_id' => $notificationRecipientId,
        ], JSON_THROW_ON_ERROR);
    }

    private function resolveRoutingKey(NotificationPriority $priority): string
    {
        return match ($priority) {
            NotificationPriority::Transactional => (string)config('rabbitmq.routing_keys.high'),
            NotificationPriority::Marketing => (string)config('rabbitmq.routing_keys.default'),
        };
    }
}
