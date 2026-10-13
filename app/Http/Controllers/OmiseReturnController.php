<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * ธนาคารพาผู้ชำระกลับมาที่นี่หลังยืนยัน 3-D Secure
 * ถามผลจาก Omise ทันที ไม่ต้องรอ webhook แล้วพากลับหน้าการจองพร้อมบอกผล
 */
class OmiseReturnController extends Controller
{
    public function __invoke(string $reference, PaymentService $payments): RedirectResponse
    {
        $reservation = Reservation::where('reference', $reference)->firstOrFail();

        abort_unless($reservation->isVisibleTo(auth()->user()), 404);

        $pending = $reservation->payments()
            ->where('provider', Payment::PROVIDER_OMISE)
            ->where('status', PaymentStatus::Pending->value)
            ->whereNotNull('provider_charge_id')
            ->get();

        foreach ($pending as $payment) {
            try {
                $payments->syncOmiseCharge($payment->provider_charge_id);
            } catch (\Throwable $e) {
                Log::warning('ตรวจผลหลัง 3-D Secure ไม่สำเร็จ', ['reference' => $reference, 'error' => $e->getMessage()]);
            }
        }

        $reservation->refresh();
        $latest = $reservation->payments()->where('provider', Payment::PROVIDER_OMISE)->latest('id')->first();

        $message = match (true) {
            $reservation->status === ReservationStatus::Confirmed => ['success', 'ชำระเงินสำเร็จ การจองได้รับการยืนยันแล้ว'],
            $latest?->status === PaymentStatus::Failed => ['error', 'ชำระไม่สำเร็จ: '.($latest->failure_message ?: 'กรุณาลองใหม่')],
            default => ['info', 'กำลังรอผลจากธนาคาร หน้านี้จะอัปเดตเองเมื่อได้ผล'],
        };

        return redirect()->route('reservations.show', $reference)->with('payment_'.$message[0], $message[1]);
    }
}
