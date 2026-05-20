<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notification\Services\NotificationDeliveryService;
use App\Infrastructure\RabbitMQ\RabbitMQConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class ConsumeNotificationsCommand extends Command
{
    protected $signature = 'notifications:consume {--limit=0 : Number of messages to process before stopping}';

    protected $description = 'Consume notification messages from RabbitMQ queues.';

    private int $processedMessages = 0;

    public function handle(
        RabbitMQConnectionFactory $connectionFactory,
        NotificationDeliveryService $deliveryService,
    ): int {
        $connection = $connectionFactory->create();
        $channel = $connection->channel();

        /*
         * Qos prefetch is set to 1 to avoid taking too many messages at once.
         * This keeps delivery predictable and makes retry/failure behaviour easier
         * to reason about.
         */
        $channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: 1,
            a_global: false,
        );

        $limit = (int) $this->option('limit');

        $callback = function (AMQPMessage $message) use ($deliveryService, $limit): void {
            try {
                $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

                $recipientId = (int) ($payload['notification_recipient_id'] ?? 0);

                if ($recipientId <= 0) {
                    throw new \InvalidArgumentException('Invalid notification_recipient_id payload.');
                }

                $deliveryService->send($recipientId);

                /*
                 * Ack is sent only after the database transaction and provider call
                 * are completed successfully. This gives us at-least-once delivery.
                 */
                $message->ack();

                $this->processedMessages++;
                $this->info(sprintf('Processed notification recipient #%d', $recipientId));

                if ($limit > 0 && $this->processedMessages >= $limit) {
                    $message->getChannel()->stopConsume();
                }
            } catch (Throwable $exception) {
                /*
                 * For the current branch we reject broken messages without requeue.
                 * Retry and dead-letter handling will be implemented in the reliability branch.
                 */
                $message->reject(false);

                $this->error($exception->getMessage());

                if ($limit > 0) {
                    $message->getChannel()->stopConsume();
                }
            }
        };

        /*
         * High-priority queue is consumed first.
         * If both queues contain messages, transactional notifications are handled
         * before marketing notifications.
         */
        $channel->basic_consume(
            queue: config('rabbitmq.queues.high'),
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: $callback,
        );

        $channel->basic_consume(
            queue: config('rabbitmq.queues.default'),
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: $callback,
        );

        $this->info('Notification consumer started.');

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

        return self::SUCCESS;
    }
}
