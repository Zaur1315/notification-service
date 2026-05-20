<?php

declare(strict_types=1);

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryAttempt extends Model
{
    protected $fillable = [
        'notification_recipient_id',
        'attempt_number',
        'provider',
        'status',
        'error_message',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NotificationRecipient::class, 'notification_recipient_id');
    }
}
