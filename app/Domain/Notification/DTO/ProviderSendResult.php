<?php

declare(strict_types=1);

namespace App\Domain\Notification\DTO;

final readonly class ProviderSendResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $errorMessage = null,
        public bool $temporaryFailure = false,
    ) {
    }
}
