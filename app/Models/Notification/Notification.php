<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a notification batch created through the API.
 *
 * One notification may contain many recipients. Actual delivery is performed
 * asynchronously at recipient level through RabbitMQ consumers.
 */
final class Notification extends Model
{
    protected $fillable = [
        'channel',
        'priority',
        'message',
        'idempotency_key',
    ];

    protected $casts = [
        /*
         * Native enum casting keeps transport values strongly typed across
         * the application and prevents invalid channel/priority states.
         */
        'channel' => NotificationChannel::class,
        'priority' => NotificationPriority::class,
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
