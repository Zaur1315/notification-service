<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\DTO\ProviderSendResult;
use App\Models\Notification\NotificationRecipient;

interface NotificationProviderInterface
{
    public function send(NotificationRecipient $recipient): ProviderSendResult;

    public function name(): string;
}
