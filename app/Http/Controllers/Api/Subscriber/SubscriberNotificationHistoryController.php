<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Subscriber;

use App\Domain\Subscriber\Services\SubscriberNotificationHistoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriber\SubscriberNotificationHistoryRequest;
use App\Models\Notification\DeliveryAttempt;
use App\Models\Notification\NotificationRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Returns notification delivery history for a specific subscriber.
 *
 * The response includes current recipient status, notification payload metadata
 * and all recorded delivery attempts.
 */
final class SubscriberNotificationHistoryController extends Controller
{
    public function __construct(
        private readonly SubscriberNotificationHistoryService $historyService,
    )
    {
    }

    public function index(
        int                                  $subscriberId,
        SubscriberNotificationHistoryRequest $request,
    ): JsonResponse
    {
        $paginator = $this->historyService->getHistory(
            subscriberId: $subscriberId,
            status: $request->status(),
            channel: $request->channel(),
            page: $request->page(),
            perPage: $request->perPage(),
        );

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn(NotificationRecipient $recipient): array => $this->formatRecipient($recipient))
                ->values(),
            'meta' => $this->formatPaginationMeta($paginator),
        ]);
    }

    private function formatRecipient(NotificationRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'subscriber_id' => $recipient->subscriber_id,
            'status' => $recipient->status->value,
            'provider_message_id' => $recipient->provider_message_id,
            'sent_at' => $recipient->sent_at?->toISOString(),
            'delivered_at' => $recipient->delivered_at?->toISOString(),
            'dropped_at' => $recipient->dropped_at?->toISOString(),
            'created_at' => $recipient->created_at?->toISOString(),
            'notification' => [
                'id' => $recipient->notification->id,
                'channel' => $recipient->notification->channel->value,
                'priority' => $recipient->notification->priority->value,
                'message' => $recipient->notification->message,
            ],
            'attempts' => $recipient->attempts
                ->map(static fn(DeliveryAttempt $attempt): array => self::formatAttempt($attempt))
                ->values(),
        ];
    }

    private static function formatAttempt(DeliveryAttempt $attempt): array
    {
        return [
            'attempt_number' => $attempt->attempt_number,
            'provider' => $attempt->provider,
            'status' => $attempt->status,
            'error_message' => $attempt->error_message,
            'attempted_at' => $attempt->attempted_at?->toISOString(),
        ];
    }

    private function formatPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
