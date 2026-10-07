<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;

final class RefundResult
{
    public function __construct(
        public readonly RefundStatus $status,
        public readonly ?string $providerRefundId = null,
        public readonly ?string $message = null,
        public readonly ?array $payload = null,
    ) {}

    public static function pending(?string $message = null): self
    {
        return new self(RefundStatus::Pending, null, $message);
    }
}
