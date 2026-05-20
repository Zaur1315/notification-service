<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Models\Notification\Notification;
use Illuminate\Support\Facades\Redis;

/**
 * Stores and resolves notification idempotency keys through Redis.
 *
 * Redis is used as a fast lookup layer to prevent duplicate notification
 * creation when the same client request is retried with the same key.
 */
final class NotificationIdempotencyService
{
    private const KEY_PREFIX = 'notification:idempotency:';
    private const TTL_SECONDS = 86400;

    public function findExistingNotificationId(?string $idempotencyKey): ?int
    {
        if ($this->isEmptyKey($idempotencyKey)) {
            return null;
        }

        $notificationId = Redis::get($this->buildKey($idempotencyKey));

        return $notificationId !== null ? (int)$notificationId : null;
    }

    public function remember(string $idempotencyKey, Notification $notification): void
    {
        /*
         * The key is hashed before storing to avoid keeping raw client-provided
         * idempotency values in Redis.
         */
        Redis::setex(
            $this->buildKey($idempotencyKey),
            self::TTL_SECONDS,
            (string)$notification->id
        );
    }

    private function isEmptyKey(?string $idempotencyKey): bool
    {
        return $idempotencyKey === null || trim($idempotencyKey) === '';
    }

    private function buildKey(string $idempotencyKey): string
    {
        return self::KEY_PREFIX . hash('sha256', $idempotencyKey);
    }
}
