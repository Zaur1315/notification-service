<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notification\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
