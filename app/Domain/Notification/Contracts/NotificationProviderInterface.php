<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\DTO\ProviderSendResult;
use App\Models\Notification\NotificationRecipient;

/**
 * Defines a common contract for all notification delivery providers.
 *
 * Real SMS, Email or other external gateways can be added later without changing
 * the delivery service. The service depends only on this abstraction.
 */
interface NotificationProviderInterface
{
    public function send(NotificationRecipient $recipient): ProviderSendResult;

    public function name(): string;
}
