<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\TemporaryProviderFailureException;
use App\Domain\Notification\Services\NotificationDeliveryService;
use App\Infrastructure\RabbitMQ\RabbitMQConnectionFactory;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Console\Command;
use InvalidArgumentException;
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
     * @throws Throwable
     */
    public function handle(
        RabbitMQConnectionFactory   $connectionFactory,
        NotificationDeliveryService $deliveryService,
    ): int
    {
        $connection = $connectionFactory->create();
        $channel = $connection->channel();

        /*
         * Prefetch = 1 prevents one worker from reserving many messages at once.
         * This keeps delivery predictable and preserves fair processing between workers.
         */
        $channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: 1,
            a_global: false,
        );

        $limit = (int)$this->option('limit');

        $callback = function (AMQPMessage $message) use ($deliveryService, $limit): void {
            $this->processMessage($message, $deliveryService, $limit);
        };

        /*
         * The high-priority queue is registered first.
         * Transactional messages therefore get a chance to be consumed before
         * marketing/default traffic when both queues contain pending messages.
         */
        $channel->basic_consume(
            queue: config('rabbitmq.queues.high'),
            callback: $callback,
        );

        $channel->basic_consume(
            queue: config('rabbitmq.queues.default'),
            callback: $callback,
        );

        $this->info('Notification consumer started.');

        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, $this->shouldStopWhenQueueIsEmpty() ? 1 : 0);
            } catch (AMQPTimeoutException $exception) {
                if ($this->shouldStopWhenQueueIsEmpty()) {
                    break;
                }

                throw $exception;
            }
        }

        $channel->close();
        $connection->close();

        return self::SUCCESS;
    }

    private function processMessage(
        AMQPMessage                 $message,
        NotificationDeliveryService $deliveryService,
        int                         $limit,
    ): void
    {
        try {
            $recipientId = $this->extractRecipientId($message);

            $deliveryService->send($recipientId);

            /*
             * Ack is sent only after provider processing and DB updates succeed.
             * This is the core of at-least-once delivery: if the worker dies before ack,
             * RabbitMQ can redeliver the message.
             */
            $message->ack();

            $this->markMessageAsProcessed();

            $this->info(sprintf('Processed notification recipient #%d', $recipientId));

            $this->stopConsumerIfLimitReached($message, $limit);
        } catch (TemporaryProviderFailureException $exception) {
            $this->handleTemporaryFailure($message, $exception);
            $this->stopConsumerIfLimitReached($message, $limit);
        } catch (Throwable $exception) {
            /*
             * Invalid payloads and unexpected non-retryable errors are rejected
             * without requeue to avoid infinite poison-message loops.
             */
            $message->reject(false);

            $this->markMessageAsProcessed();

            $this->error($exception->getMessage());

            $this->stopConsumerIfLimitReached($message, $limit);
        }
    }

    /**
     * @throws \JsonException
     */
    private function extractRecipientId(AMQPMessage $message): int
    {
        $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $recipientId = (int)($payload['notification_recipient_id'] ?? 0);

        if ($recipientId <= 0) {
            throw new InvalidArgumentException('Invalid notification_recipient_id payload.');
        }

        return $recipientId;
    }

    /**
     * @throws \JsonException
     */
    private function handleTemporaryFailure(
        AMQPMessage                       $message,
        TemporaryProviderFailureException $exception,
    ): void
    {
        $recipientId = $this->extractRecipientId($message);

        $recipient = NotificationRecipient::query()->find($recipientId);

        if (!$recipient instanceof NotificationRecipient) {
            $message->reject(false);
            $this->markMessageAsProcessed();
            $this->error('Recipient for retry was not found.');

            return;
        }

        $attemptsCount = $recipient->attempts()->count();
        $maxAttempts = (int)config('notifications.max_attempts');

        if ($attemptsCount >= $maxAttempts) {
            $recipient->update([
                'status' => NotificationStatus::Dropped,
                'dropped_at' => now(),
            ]);

            /*
             * The message is acknowledged after the business retry limit is reached.
             * At this point the message is no longer temporary-failed; it is finalized
             * as dropped in the database.
             */
            $message->ack();

            $this->markMessageAsProcessed();

            $this->error(sprintf(
                'Attempt %d/%d failed. Recipient #%d dropped: %s',
                $attemptsCount,
                $maxAttempts,
                $recipientId,
                $exception->getMessage()
            ));

            return;
        }

        /*
         * Requeue keeps the same RabbitMQ message alive for another delivery attempt.
         * The delivery attempt itself is already persisted by NotificationDeliveryService,
         * so retries remain observable in PostgreSQL.
         */
        sleep((int)config('notifications.retry_delay_seconds'));

        $message->reject();

        $this->markMessageAsProcessed();

        $this->warn(sprintf(
            'Attempt %d/%d failed. Recipient #%d requeued for retry.',
            $attemptsCount,
            $maxAttempts,
            $recipientId
        ));
    }

    private function stopConsumerIfLimitReached(AMQPMessage $message, int $limit): void
    {
        if ($limit > 0 && $this->processedMessages >= $limit) {
            $message->getChannel()->stopConsume();
        }
    }

    private function markMessageAsProcessed(): void
    {
        $this->processedMessages++;
    }

    private function shouldStopWhenQueueIsEmpty(): bool
    {
        return (bool)$this->option('once');
    }
}
