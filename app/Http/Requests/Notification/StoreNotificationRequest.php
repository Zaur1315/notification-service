<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Domain\Notification\DTO\StoreNotificationData;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => [
                'required',
                'string',
                Rule::in(NotificationChannel::values()),
            ],
            'priority' => [
                'required',
                'string',
                Rule::in(NotificationPriority::values()),
            ],
            'message' => [
                'required',
                'string',
                'max:5000',
            ],
            'recipients' => [
                'required',
                'array',
                'min:1',
                'max:10000',
            ],
            'recipients.*' => [
                'required',
                'integer',
                'distinct',
                'min:1',
            ],
        ];
    }

    public function toDto(): StoreNotificationData
    {
        return new StoreNotificationData(
            channel: NotificationChannel::from($this->string('channel')->toString()),
            priority: NotificationPriority::from($this->string('priority')->toString()),
            message: $this->string('message')->toString(),
            recipientIds: array_map('intval', $this->input('recipients')),
            idempotencyKey: $this->header('Idempotency-Key'),
        );
    }
}
