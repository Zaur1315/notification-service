<?php

declare(strict_types=1);

return [
    'max_attempts' => (int) env('NOTIFICATION_MAX_ATTEMPTS', 3),
    'retry_delay_seconds' => (int) env('NOTIFICATION_RETRY_DELAY_SECONDS', 5),
];
