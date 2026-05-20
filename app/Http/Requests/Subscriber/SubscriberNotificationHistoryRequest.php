<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscriber;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubscriberNotificationHistoryRequest extends FormRequest
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 10;
    private const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(NotificationStatus::values()),
            ],
            'channel' => [
                'nullable',
                'string',
                Rule::in(NotificationChannel::values()),
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:' . self::MAX_PER_PAGE,
            ],
        ];
    }

    public function status(): ?NotificationStatus
    {
        $status = $this->query('status');

        return $status !== null
            ? NotificationStatus::from((string) $status)
            : null;
    }

    public function channel(): ?NotificationChannel
    {
        $channel = $this->query('channel');

        return $channel !== null
            ? NotificationChannel::from((string) $channel)
            : null;
    }

    public function page(): int
    {
        return (int) $this->query('page', self::DEFAULT_PAGE);
    }

    public function perPage(): int
    {
        return (int) $this->query('per_page', self::DEFAULT_PER_PAGE);
    }
}
