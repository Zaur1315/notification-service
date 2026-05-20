<?php

declare(strict_types=1);

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'notification_user'),
    'password' => env('RABBITMQ_PASSWORD', 'notification_password'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    'exchange' => env('RABBITMQ_EXCHANGE', 'notifications.exchange'),

    'queues' => [
        'high' => env('RABBITMQ_HIGH_PRIORITY_QUEUE', 'notifications.high'),
        'default' => env('RABBITMQ_DEFAULT_QUEUE', 'notifications.default'),
    ],

    'routing_keys' => [
        'high' => 'notifications.high',
        'default' => 'notifications.default',
    ],
];
