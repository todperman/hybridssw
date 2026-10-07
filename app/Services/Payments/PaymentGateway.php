<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;

/**
 * ช่องทางรับชำระและคืนเงิน
 * ตอนนี้ใช้ manual (แอดมินบันทึกเอง) ระหว่างรอ Omise พร้อมแล้วสลับด้วย GYM_PAYMENT_DRIVER=omise
 */
interface PaymentGateway
{
    public function key(): string;

    /** ชำระออนไลน์ได้เลยหรือไม่ (manual = ผู้ชำระจ่ายช่องทางอื่นแล้วแอดมินบันทึก) */
    public function isOnline(): bool;

    /** เริ่มการชำระ คืนข้อมูลให้หน้าเว็บแสดง เช่น QR สำหรับ PromptPay หรือ null ถ้าไม่มี */
    public function startCheckout(Reservation $reservation): ?array;

    /** สั่งคืนเงิน คืนผลเป็นสถานะใหม่ของรายการคืนเงิน */
    public function refund(Payment $payment, Refund $refund): RefundResult;
}
