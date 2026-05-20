<?php

declare(strict_types=1);

namespace App\Domain\Notification\DTO;

/**
 * Immutable provider delivery result returned by notification providers.
 *
 * The DTO standardizes provider responses so the delivery service can process
 * successful sends, temporary failures and permanent failures uniformly.
 */
final readonly class ProviderSendResult
{
    public function __construct(
        public bool    $success,
        public ?string $providerMessageId = null,
        public ?string $errorMessage = null,
        public bool    $temporaryFailure = false,
    )
    {
    }
}
