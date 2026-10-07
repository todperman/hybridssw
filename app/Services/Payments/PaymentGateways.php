<?php

namespace App\Services\Payments;

use App\Models\Payment;

class PaymentGateways
{
    /** ช่องทางที่ใช้รับชำระของการจองใหม่ */
    public function current(): PaymentGateway
    {
        return $this->for(config('gym.payments.driver', Payment::PROVIDER_MANUAL));
    }

    /** ช่องทางของรายการชำระเดิม ใช้ตอนคืนเงินให้ตรงช่องทางที่จ่ายมา */
    public function for(string $provider): PaymentGateway
    {
        return match ($provider) {
            Payment::PROVIDER_OMISE => app(OmiseGateway::class),
            default => app(ManualGateway::class),
        };
    }
}
