<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', static function (): array {
    return [
        'status' => 'ok',
        'service' => 'notification-service',
    ];
});
