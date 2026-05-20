<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Models\Notification\Notification;
use Illuminate\Support\Facades\Redis;

final class NotificationIdempotencyService
{
    private const KEY_PREFIX = 'notification:idempotency:';
    private const TTL_SECONDS = 86400;

    public function findExistingNotificationId(?string $idempotencyKey): ?int
    {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            return null;
        }

        $notificationId = Redis::get($this->buildKey($idempotencyKey));

        return $notificationId !== null ? (int) $notificationId : null;
    }

    public function remember(string $idempotencyKey, Notification $notification): void
    {
        Redis::setex(
            $this->buildKey($idempotencyKey),
            self::TTL_SECONDS,
            (string) $notification->id
        );
    }

    private function buildKey(string $idempotencyKey): string
    {
        return self::KEY_PREFIX . hash('sha256', $idempotencyKey);
    }
}
