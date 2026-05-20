<?php

declare(strict_types=1);

namespace App\Infrastructure\RabbitMQ;

use App\Domain\Notification\Enums\NotificationPriority;
use PhpAmqpLib\Message\AMQPMessage;

final class NotificationQueuePublisher
{
    public function __construct(
        private readonly RabbitMQConnectionFactory $connectionFactory,
    ) {
    }

    public function publishRecipient(int $notificationRecipientId, NotificationPriority $priority): void
    {
        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();

        $routingKey = $this->resolveRoutingKey($priority);

        $message = new AMQPMessage(
            body: json_encode([
                'notification_recipient_id' => $notificationRecipientId,
            ], JSON_THROW_ON_ERROR),
            properties: [
                /*
                 * delivery_mode = 2 makes the message persistent.
                 * Together with durable queues this helps RabbitMQ keep messages
                 * after broker restart.
                 */
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ],
        );

        $channel->basic_publish(
            msg: $message,
            exchange: config('rabbitmq.exchange'),
            routing_key: $routingKey,
        );

        $channel->close();
        $connection->close();
    }

    private function resolveRoutingKey(NotificationPriority $priority): string
    {
        return match ($priority) {
            NotificationPriority::Transactional => config('rabbitmq.routing_keys.high'),
            NotificationPriority::Marketing => config('rabbitmq.routing_keys.default'),
        };
    }
}
