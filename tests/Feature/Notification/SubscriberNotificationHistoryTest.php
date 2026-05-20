<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Models\Notification\DeliveryAttempt;
use App\Models\Notification\Notification;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriberNotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_subscriber_notification_history(): void
    {
        $notification = Notification::query()->create([
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'History test message',
            'idempotency_key' => 'history-test-001',
        ]);

        $recipient = NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'subscriber_id' => 4001,
            'status' => NotificationStatus::Sent,
            'provider_message_id' => 'sms_mock_history_001',
            'sent_at' => now(),
        ]);

        DeliveryAttempt::query()->create([
            'notification_recipient_id' => $recipient->id,
            'attempt_number' => 1,
            'provider' => 'sms_mock',
            'status' => NotificationStatus::Sent->value,
            'attempted_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/subscribers/4001/notifications');

        $response->assertOk();

        $response->assertJsonPath('data.0.subscriber_id', 4001);
        $response->assertJsonPath('data.0.status', 'sent');
        $response->assertJsonPath('data.0.notification.channel', 'sms');
        $response->assertJsonPath('data.0.notification.priority', 'transactional');
        $response->assertJsonPath('data.0.attempts.0.provider', 'sms_mock');

        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_it_validates_history_filters(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/subscribers/4001/notifications?status=unknown&channel=telegram');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'status',
            'channel',
        ]);
    }
}
