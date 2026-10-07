<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Omise ผ่าน REST API ตรง ไม่ต้องลง SDK
 *
 * ชำระด้วย PromptPay QR แล้วรอ webhook charge.complete
 * webhook ไม่ได้ลงลายเซ็น จึงต้องดึง charge จาก Omise ด้วย secret key มาตรวจเองทุกครั้ง
 * ห้ามเชื่อสถานะที่มากับ payload ของ webhook เด็ดขาด
 *
 * คืนเงินใช้ได้เฉพาะช่องทางที่ Omise รองรับ ถ้าไม่รองรับจะได้สถานะคืนเงินไม่สำเร็จพร้อมเหตุผล
 * แล้วแอดมินโอนคืนเองและบันทึกผลแทน ตามข้อกำหนดที่ให้คืนตามช่องทางที่รองรับ
 */
class OmiseGateway implements PaymentGateway
{
    public function key(): string
    {
        return Payment::PROVIDER_OMISE;
    }

    public function isOnline(): bool
    {
        return true;
    }

    public function startCheckout(Reservation $reservation): ?array
    {
        // ใช้ QR เดิมถ้ายังรอผลอยู่ ไม่สร้าง charge ใหม่ทุกครั้งที่เปิดหน้า
        $existing = $reservation->payments()
            ->where('provider', $this->key())
            ->where('status', PaymentStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($existing && isset($existing->payload['qr'])) {
            return ['charge_id' => $existing->provider_charge_id, 'qr' => $existing->payload['qr']];
        }

        $charge = $this->client()->asForm()->post('/charges', [
            'amount' => $this->satang($reservation->amount),
            'currency' => 'thb',
            'source[type]' => 'promptpay',
            'expires_at' => $reservation->hold_expires_at?->toIso8601String(),
            'metadata[reservation]' => $reservation->reference,
            'return_uri' => url('/reservations/'.$reservation->reference),
        ])->throw()->json();

        $qr = data_get($charge, 'source.scannable_code.image.download_uri');

        $reservation->payments()->create([
            'payer_member_id' => $reservation->payer_member_id,
            'provider' => $this->key(),
            'provider_charge_id' => $charge['id'],
            'amount' => $reservation->amount,
            'currency' => 'THB',
            'status' => PaymentStatus::Pending,
            'payload' => ['qr' => $qr],
        ]);

        return ['charge_id' => $charge['id'], 'qr' => $qr];
    }

    /** ดึง charge จาก Omise โดยตรง ใช้ตรวจผลจาก webhook */
    public function fetchCharge(string $chargeId): array
    {
        return $this->client()->get('/charges/'.$chargeId)->throw()->json();
    }

    public function refund(Payment $payment, Refund $refund): RefundResult
    {
        if (! $payment->provider_charge_id) {
            return new RefundResult(RefundStatus::Failed, null, 'ไม่พบรหัสรายการชำระของ Omise');
        }

        $response = $this->client()->asForm()->post('/charges/'.$payment->provider_charge_id.'/refunds', [
            'amount' => $this->satang($refund->amount),
        ]);

        if ($response->failed()) {
            return new RefundResult(
                RefundStatus::Failed,
                null,
                $response->json('message') ?? 'Omise ไม่รับคำสั่งคืนเงิน',
                $response->json(),
            );
        }

        return new RefundResult(RefundStatus::Succeeded, $response->json('id'), null, $response->json());
    }

    protected function client(): PendingRequest
    {
        $secret = config('services.omise.secret_key');

        if (! $secret) {
            throw new RuntimeException('ยังไม่ได้ตั้ง OMISE_SECRET_KEY');
        }

        return Http::baseUrl(rtrim(config('services.omise.api_url'), '/'))
            ->withBasicAuth($secret, '')
            ->acceptJson()
            ->timeout(20);
    }

    protected function satang(string|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
