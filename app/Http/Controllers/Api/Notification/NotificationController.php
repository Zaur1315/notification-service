<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notification;

use App\Domain\Notification\Services\NotificationCreationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles notification creation API requests.
 *
 * The controller stays thin: validation and DTO creation are delegated to the
 * request class, while business logic is handled by NotificationCreationService.
 */
final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationCreationService $notificationCreationService,
    )
    {
    }

    /**
     * Creates a notification batch and schedules all recipients for async delivery.
     * @throws \JsonException
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationCreationService->create($request->toDto());

        return response()->json(
            [
                'data' => [
                    'id' => $notification->id,
                    'channel' => $notification->channel->value,
                    'priority' => $notification->priority->value,
                    'message' => $notification->message,
                    'recipients_count' => $notification->recipients->count(),
                    'created_at' => $notification->created_at?->toISOString(),
                ],
            ],
            Response::HTTP_CREATED
        );
    }
}
