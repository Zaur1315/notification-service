<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Domain\Notification\DTO\StoreNotificationData;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates notification creation payload and converts it to a domain DTO.
 *
 * Keeping DTO creation here allows controllers to stay thin and prevents
 * domain services from depending on raw HTTP request data.
 */
final class StoreNotificationRequest extends FormRequest
{
    private const MAX_MESSAGE_LENGTH = 5000;
    private const MAX_RECIPIENTS_COUNT = 10000;

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
                'max:' . self::MAX_MESSAGE_LENGTH,
            ],
            'recipients' => [
                'required',
                'array',
                'min:1',
                'max:' . self::MAX_RECIPIENTS_COUNT,
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
            recipientIds: $this->recipientIds(),
            idempotencyKey: $this->header('Idempotency-Key'),
        );
    }

    /**
     * Returns validated recipient IDs as integers.
     *
     * @return int[]
     */
    private function recipientIds(): array
    {
        return array_map('intval', $this->input('recipients', []));
    }
}
