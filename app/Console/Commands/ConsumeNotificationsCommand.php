<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\TemporaryProviderFailureException;
use App\Domain\Notification\Services\NotificationDeliveryService;
use App\Infrastructure\RabbitMQ\RabbitMQConnectionFactory;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class ConsumeNotificationsCommand extends Command
{
    protected $signature = 'notifications:consume
        {--limit=0 : Number of messages to process before stopping}
        {--once : Stop when no message is currently available}';

    protected $description = 'Consume notification messages from RabbitMQ queues.';

    private int $processedMessages = 0;

    /**
     * @throws \Exception
     */
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
                if ($exception instanceof TemporaryProviderFailureException) {
                    $this->handleTemporaryFailure($message, $exception);

                    if ($limit > 0) {
                        $message->getChannel()->stopConsume();
                    }

                    return;
                }

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
            try {
                $channel->wait(null, false, $this->option('once') ? 1 : 0);
            } catch (AMQPTimeoutException $exception) {
                if ($this->option('once')) {
                    break;
                }
                throw $exception;
            }
        }

        $channel->close();
        $connection->close();

        return self::SUCCESS;
    }

    private function handleTemporaryFailure(AMQPMessage $message, TemporaryProviderFailureException $exception): void
    {
        $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $recipientId = (int) ($payload['notification_recipient_id'] ?? 0);

        $recipient = NotificationRecipient::query()->find($recipientId);

        if (!$recipient instanceof NotificationRecipient) {
            $message->reject(false);
            $this->error('Recipient for retry was not found.');

            return;
        }

        $attemptsCount = $recipient->attempts()->count();
        $maxAttempts = (int) config('notifications.max_attempts');

        if ($attemptsCount >= $maxAttempts) {
            $recipient->update([
                'status' => NotificationStatus::Dropped,
                'dropped_at' => now(),
            ]);

            $message->ack();

            $this->processedMessages++;

            $this->error(
                sprintf(
                    'Recipient #%d dropped after %d attempts: %s',
                    $recipientId,
                    $attemptsCount,
                    $exception->getMessage()
                )
            );

            return;
        }

        /*
         * The message is rejected with requeue=true to let RabbitMQ deliver it again.
         * This keeps delivery at-least-once while the service decides when to stop retrying.
         */
        sleep((int) config('notifications.retry_delay_seconds'));

        $message->reject(true);

        $this->processedMessages++;

        $this->warn(
            sprintf(
                'Temporary failure for recipient #%d. Requeued for retry. Attempts: %d/%d',
                $recipientId,
                $attemptsCount,
                $maxAttempts
            )
        );
    }
}
