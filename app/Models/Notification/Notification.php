<?php

declare(strict_types=1);

namespace app\Models\Notification;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Notification extends Model
{
    protected $fillable = [
        'channel',
        'priority',
        'message',
        'idempotency_key',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'priority' => NotificationPriority::class,
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
