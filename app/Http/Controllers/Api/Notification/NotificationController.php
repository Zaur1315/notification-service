<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notification;

use App\Domain\Notification\Services\NotificationCreationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationCreationService $notificationCreationService,
    )
    {
    }

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
