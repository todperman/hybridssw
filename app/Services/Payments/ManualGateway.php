<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;

/**
 * ผู้ชำระจ่ายด้วยช่องทางที่สาขาแจ้งไว้ เช่นโอนหรือเงินสด แล้วแอดมินบันทึกรับชำระในหลังบ้าน
 * คืนเงินก็โอนคืนเองแล้วกดบันทึกผล ระบบไม่ได้ส่งเงินจริง
 */
class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return Payment::PROVIDER_MANUAL;
    }

    public function isOnline(): bool
    {
        return false;
    }

    public function startCheckout(Reservation $reservation): ?array
    {
        return null;
    }

    public function refund(Payment $payment, Refund $refund): RefundResult
    {
        return RefundResult::pending('โอนคืนให้ผู้ชำระเงินแล้วบันทึกผลในหลังบ้าน');
    }
}
