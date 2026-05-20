<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Infrastructure\RabbitMQ\RabbitMQConnectionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class NotificationQueueProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();

        Artisan::call('rabbitmq:setup');

        $this->purgeRabbitMqQueues();
    }

    public function test_it_processes_notification_from_queue_and_marks_recipients_as_sent(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => 'queue-processing-test-001',
        ])->postJson('/api/notifications', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Queue processing test message',
            'recipients' => [5001, 5002],
        ]);

        $response->assertCreated();

        Artisan::call('notifications:consume', [
            '--limit' => 2,
            '--once' => true,
        ]);

        foreach ([5001, 5002] as $subscriberId) {
            $this->assertDatabaseHas('notification_recipients', [
                'subscriber_id' => $subscriberId,
                'status' => NotificationStatus::Sent->value,
            ]);
        }

        $this->assertDatabaseCount('delivery_attempts', 2);

        $this->assertDatabaseHas('delivery_attempts', [
            'provider' => 'sms_mock',
            'status' => NotificationStatus::Sent->value,
        ]);
    }

    private function purgeRabbitMqQueues(): void
    {
        /** @var RabbitMQConnectionFactory $connectionFactory */
        $connectionFactory = app(RabbitMQConnectionFactory::class);

        $connection = $connectionFactory->create();
        $channel = $connection->channel();

        foreach (config('rabbitmq.queues') as $queue) {
            $channel->queue_purge($queue);
        }

        $channel->close();
        $connection->close();
    }
}
