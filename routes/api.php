<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Subscriber\SubscriberNotificationHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static function (): array {
    return [
        'status' => 'ok',
        'service' => 'notification-service',
    ];
});

Route::post('/notifications', [NotificationController::class, 'store']);

Route::get(
    '/subscribers/{subscriberId}/notifications',
    [SubscriberNotificationHistoryController::class, 'index']
)->whereNumber('subscriberId');
