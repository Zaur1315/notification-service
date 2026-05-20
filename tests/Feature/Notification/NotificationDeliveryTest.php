<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Exceptions\TemporaryProviderFailureException;
use App\Domain\Notification\Services\NotificationDeliveryService;
use App\Models\Notification\Notification;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers recipient-level delivery logic.
 *
 * These tests verify provider calls, status transitions and delivery attempt
 * persistence without going through the HTTP API or RabbitMQ consumer.
 */
final class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_queued_notification_recipient(): void
    {
        $notification = $this->createNotification();

        $recipient = NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'subscriber_id' => 3001,
            'status' => NotificationStatus::Queued,
        ]);

        $this->deliveryService()->send((int)$recipient->id);

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

    public function test_it_records_temporary_failures_as_delivery_attempts(): void
    {
        $notification = $this->createNotification('delivery-retry-test-001');

        $recipient = NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'subscriber_id' => 999,
            'status' => NotificationStatus::Queued,
        ]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->deliveryService()->send((int)$recipient->id);
            } catch (TemporaryProviderFailureException) {
                // Expected temporary failure from mock provider.
            }
        }

        /*
         * NotificationDeliveryService records failed attempts but does not decide
         * when to mark a recipient as dropped. The consumer owns retry limit logic.
         */
        $recipient->refresh();

        self::assertSame(NotificationStatus::Queued, $recipient->status);

        $this->assertDatabaseCount('delivery_attempts', 3);

        $this->assertDatabaseHas('delivery_attempts', [
            'notification_recipient_id' => $recipient->id,
            'attempt_number' => 3,
            'provider' => 'sms_mock',
            'status' => NotificationStatus::Dropped->value,
            'error_message' => 'Temporary SMS provider failure.',
        ]);
    }

    private function createNotification(string $idempotencyKey = 'delivery-test-001'): Notification
    {
        return Notification::query()->create([
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Delivery test message',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function deliveryService(): NotificationDeliveryService
    {
        /** @var NotificationDeliveryService $service */
        $service = app(NotificationDeliveryService::class);

        return $service;
    }
}
