<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Services\Payments\Omise\OmiseClient;
use App\Services\Payments\Omise\OmiseException;
use Illuminate\Support\Facades\DB;

/**
 * รับชำระผ่าน Omise: พร้อมเพย์ (QR) และบัตรเครดิต/เดบิต (มี 3-D Secure)
 *
 * - ผลชำระตัดสินจากข้อมูลที่ดึงจาก Omise ด้วย secret key เท่านั้น ไม่เชื่อสิ่งที่มากับหน้าเว็บหรือ webhook
 * - ข้อมูลบัตรถูกเข้ารหัสเป็น token ในเบราว์เซอร์ด้วย Omise.js ไม่ผ่านเซิร์ฟเวอร์นี้เลย
 * - QR หมดอายุพร้อมเวลากันจอง ผู้ชำระจึงจ่ายหลังเวลาถูกปล่อยไปแล้วไม่ได้
 * - คืนเงินบัตรผ่าน Omise ได้ ส่วนพร้อมเพย์ Omise คืนให้ไม่ได้ ต้องโอนคืนเองแล้วบันทึกผล
 */
class OmiseGateway implements PaymentGateway
{
    /** ขั้นต่ำและเพดานต่อรายการของ Omise (สตางค์) */
    public const MIN_AMOUNT = 2000;

    public const MAX_AMOUNT = 15000000;

    public function __construct(protected OmiseClient $omise) {}

    public function key(): string
    {
        return Payment::PROVIDER_OMISE;
    }

    public function isOnline(): bool
    {
        return true;
    }

    /** @return array<int, string> */
    public function methods(): array
    {
        return array_values(array_intersect(config('services.omise.methods', []), [Payment::METHOD_PROMPTPAY, Payment::METHOD_CARD]));
    }

    public function isTestMode(): bool
    {
        return $this->omise->isTestMode();
    }

    public function publicKey(): ?string
    {
        return $this->omise->publicKey();
    }

    /**
     * QR พร้อมเพย์ของการจองนี้ ใช้ QR เดิมถ้ายังไม่หมดอายุ ไม่สร้างใหม่ทุกครั้งที่เปิดหน้า
     * ล็อกแถวการจองไว้ กดปุ่มรัว ๆ หรือเปิดสองแท็บก็ได้ QR เดียว
     *
     * @return array{charge_id: string, qr: ?string, expires_at: ?string}
     */
    public function startCheckout(Reservation $reservation): ?array
    {
        $this->assertMethod(Payment::METHOD_PROMPTPAY);

        return DB::transaction(function () use ($reservation) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($reservation);

            $existing = $reservation->payments()
                ->where('provider', $this->key())
                ->where('method', Payment::METHOD_PROMPTPAY)
                ->where('status', PaymentStatus::Pending->value)
                ->latest('id')
                ->first();

            if ($existing && ($existing->payload['qr'] ?? null) && now()->lt($existing->payload['expires_at'] ?? now())) {
                return $this->qrResult($existing);
            }

            $charge = $this->omise->createCharge($this->chargeParams($reservation) + [
                'source[type]' => 'promptpay',
                // QR หมดอายุพร้อมเวลากันจอง (Omise ยอมได้ไม่เกิน 24 ชั่วโมง)
                'expires_at' => $reservation->hold_expires_at->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);

            $payment = $reservation->payments()->create([
                'payer_member_id' => $reservation->payer_member_id,
                'provider' => $this->key(),
                'method' => Payment::METHOD_PROMPTPAY,
                'provider_charge_id' => $charge['id'],
                'amount' => $reservation->amount,
                'currency' => 'THB',
                'status' => PaymentStatus::Pending,
                'payload' => [
                    'qr' => data_get($charge, 'source.scannable_code.image.download_uri'),
                    'expires_at' => $charge['expires_at'] ?? $reservation->hold_expires_at->toIso8601String(),
                ],
            ]);

            return $this->qrResult($payment);
        });
    }

    /**
     * ตัดบัตรด้วย token จาก Omise.js
     * บัตรที่ต้องยืนยัน 3-D Secure จะได้ลิงก์ไปหน้าธนาคาร กลับมาที่ return_uri แล้วค่อยรู้ผล
     *
     * @return array{status: string, authorize_uri?: string, message?: string, charge: array}
     */
    public function chargeCard(Reservation $reservation, string $token, ?string $ip = null): array
    {
        $this->assertMethod(Payment::METHOD_CARD);

        if (! preg_match('/^tokn_[a-zA-Z0-9_]+$/', $token)) {
            throw new OmiseException('ข้อมูลบัตรไม่ถูกต้อง กรุณากรอกใหม่');
        }

        $payment = DB::transaction(function () use ($reservation) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($reservation);

            return $reservation->payments()->create([
                'payer_member_id' => $reservation->payer_member_id,
                'provider' => $this->key(),
                'method' => Payment::METHOD_CARD,
                'amount' => $reservation->amount,
                'currency' => 'THB',
                'status' => PaymentStatus::Pending,
            ]);
        });

        try {
            $charge = $this->omise->createCharge($this->chargeParams($reservation) + array_filter([
                'card' => $token,
                'return_uri' => route('reservations.payment-return', $reservation->reference),
                'ip' => $ip,
                'metadata[payment_id]' => (string) $payment->id,
            ]));
        } catch (OmiseException $e) {
            $payment->update(['status' => PaymentStatus::Failed, 'failure_message' => $e->getMessage()]);

            throw $e;
        }

        $payment->update(['provider_charge_id' => $charge['id']]);

        return ['charge' => $charge] + match (true) {
            ($charge['status'] ?? null) === 'pending' && filled($charge['authorize_uri'] ?? null) => ['status' => 'redirect', 'authorize_uri' => $charge['authorize_uri']],
            ($charge['status'] ?? null) === 'successful' => ['status' => 'successful'],
            ($charge['status'] ?? null) === 'pending' => ['status' => 'pending'],
            default => ['status' => 'failed', 'message' => $this->failureMessage($charge)],
        };
    }

    public function fetchCharge(string $chargeId): array
    {
        return $this->omise->retrieveCharge($chargeId);
    }

    public function refund(Payment $payment, Refund $refund): RefundResult
    {
        if (! $payment->refundableOnline()) {
            return RefundResult::pending('ชำระด้วยพร้อมเพย์ Omise คืนเงินให้ไม่ได้ ต้องโอนคืนให้ผู้ชำระเองแล้วกดบันทึกผลคืนเงิน');
        }

        if (! $payment->provider_charge_id) {
            return new RefundResult(RefundStatus::Failed, null, 'ไม่พบรหัสรายการชำระของ Omise');
        }

        try {
            $result = $this->omise->refund($payment->provider_charge_id, $this->satang($refund->amount), [
                'refund_id' => (string) $refund->id,
                'reservation' => $payment->reservation?->reference,
            ]);
        } catch (OmiseException $e) {
            return new RefundResult(RefundStatus::Failed, null, $e->getMessage());
        }

        return new RefundResult(RefundStatus::Succeeded, $result['id'] ?? null, null, [
            'id' => $result['id'] ?? null,
            'amount' => $result['amount'] ?? null,
            'status' => $result['status'] ?? null,
        ]);
    }

    /** ข้อความที่แสดงให้ผู้ชำระเห็นเมื่อบัตรไม่ผ่าน */
    public function failureMessage(array $charge): string
    {
        $code = $charge['failure_code'] ?? null;

        return match ($code) {
            'insufficient_fund' => 'ยอดเงินในบัตรไม่พอ',
            'stolen_or_lost_card' => 'บัตรนี้ใช้ชำระไม่ได้ กรุณาใช้บัตรอื่น',
            'failed_fraud_check' => 'ธนาคารไม่อนุมัติรายการนี้ กรุณาใช้บัตรอื่น',
            'invalid_account_number', 'invalid_security_code' => 'ข้อมูลบัตรไม่ถูกต้อง',
            'payment_cancelled' => 'ยกเลิกการชำระแล้ว',
            'failed_processing', 'payment_rejected' => 'ธนาคารปฏิเสธรายการ กรุณาลองบัตรอื่นหรือติดต่อธนาคาร',
            default => $charge['failure_message'] ?? 'ชำระไม่สำเร็จ กรุณาลองใหม่',
        };
    }

    protected function chargeParams(Reservation $reservation): array
    {
        return [
            'amount' => $this->satang($reservation->amount),
            'currency' => 'thb',
            'description' => 'Hybrid SSW '.$reservation->reference.' '.$reservation->starts_at->format('d/m/Y H:i').' '.$reservation->hours.'h',
            'metadata[reservation]' => $reservation->reference,
        ];
    }

    protected function assertPayable(Reservation $reservation): void
    {
        if ($reservation->status !== \App\Enums\ReservationStatus::PendingPayment || ! $reservation->hold_expires_at?->isFuture()) {
            throw new OmiseException('การจองนี้ไม่อยู่ในสถานะรอชำระเงินแล้ว');
        }

        if ($reservation->payments()->where('status', PaymentStatus::Succeeded->value)->exists()) {
            throw new OmiseException('การจองนี้ชำระเงินแล้ว');
        }

        $amount = $this->satang($reservation->amount);

        if ($amount < self::MIN_AMOUNT || $amount > self::MAX_AMOUNT) {
            throw new OmiseException('ยอดชำระต้องอยู่ระหว่าง 20 ถึง 150,000 บาท');
        }
    }

    protected function assertMethod(string $method): void
    {
        if (! in_array($method, $this->methods(), true)) {
            throw new OmiseException('ช่องทางชำระนี้ยังไม่เปิดใช้');
        }
    }

    protected function qrResult(Payment $payment): array
    {
        return [
            'charge_id' => $payment->provider_charge_id,
            'qr' => $payment->payload['qr'] ?? null,
            'expires_at' => $payment->payload['expires_at'] ?? null,
        ];
    }

    public function satang(string|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
