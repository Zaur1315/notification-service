<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Notification\Enums\NotificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class NotificationCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    public function test_it_creates_notification_with_recipients(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => 'test-create-notification-001',
        ])->postJson('/api/notifications', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Your verification code is 123456',
            'recipients' => [1001, 1002, 1003],
        ]);

        $response->assertCreated();

        $response->assertJsonPath('data.channel', 'sms');
        $response->assertJsonPath('data.priority', 'transactional');
        $response->assertJsonPath('data.recipients_count', 3);

        $this->assertDatabaseHas('notifications', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Your verification code is 123456',
            'idempotency_key' => 'test-create-notification-001',
        ]);

        foreach ([1001, 1002, 1003] as $subscriberId) {
            $this->assertDatabaseHas('notification_recipients', [
                'subscriber_id' => $subscriberId,
                'status' => NotificationStatus::Queued->value,
            ]);
        }
    }

    public function test_it_returns_validation_errors_for_invalid_payload(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/notifications', [
            'channel' => 'telegram',
            'priority' => 'urgent',
            'message' => '',
            'recipients' => [],
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'channel',
            'priority',
            'message',
            'recipients',
        ]);
    }

    public function test_it_returns_existing_notification_for_same_idempotency_key(): void
    {
        $payload = [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Idempotency test message',
            'recipients' => [2001, 2002],
        ];

        $firstResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => 'test-idempotency-key-001',
        ])->postJson('/api/notifications', $payload);

        $secondResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => 'test-idempotency-key-001',
        ])->postJson('/api/notifications', $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        self::assertSame(
            $firstResponse->json('data.id'),
            $secondResponse->json('data.id')
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_recipients', 2);
    }
}
