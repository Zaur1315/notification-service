<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Domain\Notification\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents delivery state for one subscriber within a notification batch.
 *
 * Queue messages are recipient-based, so each subscriber can be processed,
 * retried and tracked independently.
 */
final class NotificationRecipient extends Model
{
    protected $fillable = [
        'notification_id',
        'subscriber_id',
        'status',
        'provider_message_id',
        'sent_at',
        'delivered_at',
        'dropped_at',
    ];

    protected $casts = [
        /*
         * Enum casting keeps status transitions explicit and prevents accidental
         * use of unsupported delivery states.
         */
        'status' => NotificationStatus::class,
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'dropped_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }
}
