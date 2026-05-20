<?php

declare(strict_types=1);

namespace App\Domain\Subscriber\Services;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads notification delivery history for a specific subscriber.
 *
 * The service is read-only and returns paginated recipient-level records with
 * notification data and delivery attempts for API history responses.
 */
final class SubscriberNotificationHistoryService
{
    public function getHistory(
        int                  $subscriberId,
        ?NotificationStatus  $status,
        ?NotificationChannel $channel,
        int                  $page,
        int                  $perPage,
    ): LengthAwarePaginator
    {
        return NotificationRecipient::query()
            ->with(['notification', 'attempts'])
            ->where('subscriber_id', $subscriberId)
            ->when($status !== null, static function (Builder $query) use ($status): void {
                $query->where('status', $status->value);
            })
            ->when($channel !== null, static function (Builder $query) use ($channel): void {
                $query->whereHas('notification', static function (Builder $notificationQuery) use ($channel): void {
                    $notificationQuery->where('channel', $channel->value);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page,
            );
    }
}
