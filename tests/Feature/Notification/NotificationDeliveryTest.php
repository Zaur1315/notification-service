<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Services\NotificationDeliveryService;
use App\Models\Notification\Notification;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_queued_notification_recipient(): void
    {
        $notification = Notification::query()->create([
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Delivery test message',
            'idempotency_key' => 'delivery-test-001',
        ]);

        $recipient = NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'subscriber_id' => 3001,
            'status' => NotificationStatus::Queued,
        ]);

        /** @var NotificationDeliveryService $service */
        $service = app(NotificationDeliveryService::class);

        $service->send((int) $recipient->id);

        $recipient->refresh();

        self::assertSame(NotificationStatus::Sent, $recipient->status);
        self::assertNotNull($recipient->provider_message_id);
        self::assertNotNull($recipient->sent_at);

        $this->assertDatabaseHas('delivery_attempts', [
            'notification_recipient_id' => $recipient->id,
            'attempt_number' => 1,
            'provider' => 'sms_mock',
            'status' => NotificationStatus::Sent->value,
        ]);
    }

    public function test_it_drops_recipient_after_max_temporary_failures(): void
    {
        config()->set('notifications.max_attempts', 3);

        $notification = Notification::query()->create([
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Retry delivery test message',
            'idempotency_key' => 'delivery-retry-test-001',
        ]);

        $recipient = NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'subscriber_id' => 999,
            'status' => NotificationStatus::Queued,
        ]);

        /** @var NotificationDeliveryService $service */
        $service = app(NotificationDeliveryService::class);

        for ($i = 1; $i <= 3; $i++) {
            try {
                $service->send((int) $recipient->id);
            } catch (\App\Domain\Notification\Exceptions\TemporaryProviderFailureException) {
                // Expected temporary failure from mock provider.
            }
        }

        $recipient->update([
            'status' => NotificationStatus::Dropped,
            'dropped_at' => now(),
        ]);

        $recipient->refresh();

        self::assertSame(NotificationStatus::Dropped, $recipient->status);
        self::assertNotNull($recipient->dropped_at);

        $this->assertDatabaseCount('delivery_attempts', 3);

        $this->assertDatabaseHas('delivery_attempts', [
            'notification_recipient_id' => $recipient->id,
            'attempt_number' => 3,
            'provider' => 'sms_mock',
            'status' => NotificationStatus::Dropped->value,
            'error_message' => 'Temporary SMS provider failure.',
        ]);
    }
}
