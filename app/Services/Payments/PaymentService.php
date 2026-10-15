<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservations\ReservationLocker;
use App\Services\Reservations\ReservationNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * รับผลชำระ ยืนยันการจอง และคืนเงิน
 *
 * กติกาที่ห้ามพัง
 * - ผลชำระเดียวกันส่งมากี่ครั้งก็บันทึกครั้งเดียว (unique provider + provider_charge_id)
 * - ชำระไม่สำเร็จไม่มีทางทำให้การจองถูกยืนยัน
 * - ชำระสำเร็จหลังหมดอายุ ยืนยันได้เฉพาะเมื่อเวลาเดิมยังว่างครบ ไม่งั้นต้องให้แอดมินตรวจและคืนเงิน
 *   ห้ามยืนยันทับช่วงที่คนอื่นจองไปแล้วเด็ดขาด
 */
class PaymentService
{
    public function __construct(
        protected ReservationLocker $locker,
        protected ReservationNotifier $notify,
        protected PaymentGateways $gateways,
    ) {}

    /** บันทึกผลชำระสำเร็จ ใช้ได้ทั้งแอดมินบันทึกเองและ webhook ของ Omise */
    public function recordSuccess(
        Reservation $reservation,
        string $provider,
        string|float $amount,
        ?string $chargeId = null,
        ?User $recordedBy = null,
        ?string $note = null,
        ?array $payload = null,
    ): Payment {
        if ($chargeId) {
            $existing = Payment::where('provider', $provider)->where('provider_charge_id', $chargeId)->first();

            if ($existing?->status === PaymentStatus::Succeeded) {
                return $existing;
            }
        }

        $amountMatches = bccomp((string) $amount, (string) $reservation->amount, 2) === 0;

        if ($provider === Payment::PROVIDER_MANUAL && ! $amountMatches) {
            throw ReservationException::amountMismatch();
        }

        [$payment, $outcome] = DB::transaction(function () use ($reservation, $provider, $amount, $chargeId, $recordedBy, $note, $payload, $amountMatches) {
            $r = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            $alreadyPaid = $r->payments()->where('status', PaymentStatus::Succeeded->value)->exists();

            if ($alreadyPaid && $provider === Payment::PROVIDER_MANUAL) {
                throw ReservationException::alreadyPaid();
            }

            $attributes = [
                'payer_member_id' => $r->payer_member_id,
                'amount' => $amount,
                'currency' => 'THB',
                'status' => PaymentStatus::Succeeded,
                'paid_at' => now(),
                'recorded_by_user_id' => $recordedBy?->id,
                'note' => $note,
                'payload' => $payload,
            ];

            // ช่องทางย่อยที่ Omise บอกมา ใส่เฉพาะเมื่อรู้ ไม่ทับค่าที่บันทึกไว้ตอนเริ่มชำระด้วย null
            if ($method = $payload['method'] ?? null) {
                $attributes['method'] = $method;
            }

            $payment = $chargeId
                ? Payment::updateOrCreate(['provider' => $provider, 'provider_charge_id' => $chargeId], ['reservation_id' => $r->id] + $attributes)
                : $r->payments()->create(['provider' => $provider] + $attributes);

            $outcome = match (true) {
                $alreadyPaid, ! $amountMatches => 'review',
                $r->status === ReservationStatus::PendingPayment && $r->hold_expires_at?->isFuture() => $this->confirm($r),
                $r->status === ReservationStatus::PendingPayment, $r->status === ReservationStatus::Expired => $this->confirmLate($r),
                default => 'review',
            };

            if ($outcome === 'review') {
                $payment->update(['needs_review' => true]);
            }

            return [$payment->refresh(), $outcome];
        });

        $reservation->refresh();

        AuditLog::record('reservation.payment_recorded', $reservation, null, [
            'payment_id' => $payment->id,
            'provider' => $provider,
            'amount' => (string) $payment->amount,
            'outcome' => $outcome,
        ], $note, $recordedBy);

        if ($outcome === 'review') {
            $this->notify->send(
                $reservation,
                $this->notify->admins($reservation->branch),
                'มีรายการชำระเงินต้องตรวจสอบ',
                'ได้รับเงิน '.number_format((float) $payment->amount, 2).' บาท สำหรับ '.$reservation->reference
                    .' แต่ยืนยันการจองไม่ได้ (หมดอายุแล้วเวลาถูกจองไปแล้ว ยอดไม่ตรง หรือชำระซ้ำ) กรุณาตรวจและคืนเงิน',
                url('/admin/payments'),
            );
        } else {
            $this->notify->send(
                $reservation,
                $this->notify->everyone($reservation),
                'ยืนยันการจองแล้ว',
                'ชำระเงินสำเร็จ การจอง '.$reservation->dateLabel().' '.$reservation->timeLabel().' ได้รับการยืนยัน',
                $this->notify->urlFor($reservation),
            );
        }

        return $payment;
    }

    public function recordFailure(string $provider, string $chargeId, ?string $message = null, ?array $payload = null): void
    {
        Payment::where('provider', $provider)
            ->where('provider_charge_id', $chargeId)
            ->where('status', PaymentStatus::Pending->value)
            ->update([
                'status' => PaymentStatus::Failed->value,
                'failure_message' => $message,
                'payload' => $payload ? json_encode($payload) : null,
            ]);
    }

    protected function confirm(Reservation $r): string
    {
        $r->update([
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'hold_expires_at' => null,
        ]);

        return 'confirmed';
    }

    /**
     * ชำระหลังหมดเวลากัน
     * ถ้ายังถือล็อกอยู่ (job ยังไม่ได้ปล่อย) ยืนยันได้เลยเพราะไม่มีใครเอาช่วงนี้ไปได้
     * ถ้าปล่อยล็อกไปแล้ว ลองล็อกใหม่ ได้ครบก็ยืนยัน ไม่ได้ต้องให้แอดมินคืนเงิน
     */
    protected function confirmLate(Reservation $r): string
    {
        if ($r->locks()->exists()) {
            return $this->confirm($r);
        }

        try {
            DB::transaction(fn () => $this->locker->lock($r->load('participants')));
        } catch (QueryException $e) {
            if (ReservationLocker::isDuplicate($e)) {
                return 'review';
            }

            throw $e;
        }

        return $this->confirm($r);
    }

    // ------------------------------------------------------------------
    // คืนเงิน
    // ------------------------------------------------------------------

    /** คืนเต็มจำนวนให้ผู้ชำระเงินเดิม ผ่านช่องทางเดียวกับที่จ่ายมา */
    public function refundInFull(Reservation $reservation, Payment $payment, User $admin, string $reason): Refund
    {
        $refund = $reservation->refunds()->create([
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'reason' => $reason,
            'status' => RefundStatus::Pending,
            'approved_by_user_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $reservation->update(['refund_status' => RefundStatus::Pending]);

        AuditLog::record('refund.created', $reservation, null, [
            'refund_id' => $refund->id,
            'amount' => (string) $refund->amount,
            'payer' => $payment->payer?->user?->name,
        ], $reason, $admin);

        $result = rescue(
            fn () => $this->gateways->for($payment->provider)->refund($payment, $refund),
            fn (\Throwable $e) => new RefundResult(RefundStatus::Failed, null, $e->getMessage()),
            report: true,
        );

        if ($result->status !== RefundStatus::Pending) {
            $this->settleRefund($refund, $result->status, $admin, $result->message, $result->providerRefundId, $result->payload);
        }

        return $refund->refresh();
    }

    /**
     * ปิดรายการชำระที่ต้องตรวจสอบ (ชำระช้าจนเวลาถูกจองไปแล้ว ยอดไม่ตรง หรือชำระซ้ำ)
     * คืนเงินเต็มจำนวน หรือบันทึกว่าตรวจแล้วไม่ต้องคืน พร้อมเหตุผล
     */
    public function resolveReview(Payment $payment, User $admin, string $reason, bool $refund): ?Refund
    {
        if (! $payment->needs_review) {
            throw new ReservationException('รายการนี้ตรวจสอบไปแล้ว', 'already_reviewed');
        }

        $reservation = $payment->reservation;

        $result = $refund ? $this->refundInFull($reservation, $payment, $admin, $reason) : null;

        $payment->update(['needs_review' => false]);

        AuditLog::record('payment.reviewed', $reservation, null, [
            'payment_id' => $payment->id,
            'refunded' => $refund,
        ], $reason, $admin);

        return $result;
    }

    /** บันทึกผลการคืนเงิน ใช้ทั้งผลจาก Omise และแอดมินที่โอนคืนเองแล้วกดบันทึก */
    public function settleRefund(Refund $refund, RefundStatus $status, ?User $actor, ?string $message = null, ?string $providerRefundId = null, ?array $payload = null): Refund
    {
        $before = ['status' => $refund->status->value];

        $refund->update([
            'status' => $status,
            'failure_message' => $status === RefundStatus::Failed ? $message : null,
            'provider_refund_id' => $providerRefundId ?? $refund->provider_refund_id,
            'processed_at' => $status === RefundStatus::Pending ? null : now(),
            'payload' => $payload ?? $refund->payload,
        ]);

        $reservation = $refund->reservation;
        $reservation->update(['refund_status' => $status]);

        AuditLog::record('refund.updated', $reservation, $before, ['status' => $status->value, 'refund_id' => $refund->id], $message, $actor);

        if ($status === RefundStatus::Succeeded) {
            $this->notify->send(
                $reservation,
                $this->notify->payer($reservation),
                'คืนเงินสำเร็จ',
                'คืนเงิน '.number_format((float) $refund->amount, 2).' บาท สำหรับการจอง '.$reservation->reference.' เรียบร้อยแล้ว',
                $this->notify->urlFor($reservation),
            );
        }

        return $refund->refresh();
    }

    // ------------------------------------------------------------------
    // Omise webhook
    // ------------------------------------------------------------------

    /** ตรวจผลกับ Omise โดยตรงก่อนเชื่อ payload ทุกครั้ง */
    public function handleOmiseEvent(array $event): void
    {
        $key = $event['key'] ?? null;

        // ใช้แค่รหัสรายการจาก event แล้วไปดึงของจริงจาก Omise เอง ไม่เชื่อสถานะใน payload
        if (in_array($key, ['charge.create', 'charge.complete', 'charge.update', 'charge.capture', 'charge.reverse'], true)) {
            $chargeId = data_get($event, 'data.id');

            if (is_string($chargeId) && str_starts_with($chargeId, 'chrg_')) {
                $this->syncOmiseCharge($chargeId);
            }

            return;
        }

        if ($key === 'refund.create') {
            $this->syncOmiseRefund((string) data_get($event, 'data.charge'), (string) data_get($event, 'data.id'));
        }
    }

    /**
     * ดึงสถานะ charge จาก Omise แล้วบันทึกผล
     * ใช้ทั้งจาก webhook หน้าการจองที่รอผล หน้ากลับจาก 3-D Secure และงานกระทบยอดอัตโนมัติ
     * เรียกซ้ำกี่ครั้งก็ได้ผลเดิม เพราะบันทึกยอดสำเร็จซ้ำไม่ได้
     */
    public function syncOmiseCharge(string $chargeId): void
    {
        $charge = app(OmiseGateway::class)->fetchCharge($chargeId);

        $payment = Payment::where('provider', Payment::PROVIDER_OMISE)->where('provider_charge_id', $chargeId)->first();
        $reference = data_get($charge, 'metadata.reservation');
        $reservation = $payment?->reservation ?? ($reference ? Reservation::where('reference', $reference)->first() : null);

        // charge ที่ระบบนี้ไม่ได้สร้าง หรือเลขการจองไม่ตรงกับที่บันทึกไว้ ไม่แตะ
        if (! $reservation || ($reference && $reference !== $reservation->reference)) {
            Log::warning('ข้ามผลชำระ Omise ที่ไม่ตรงกับการจองในระบบ', ['charge' => $chargeId, 'reference' => $reference]);

            return;
        }

        $status = $charge['status'] ?? null;

        if ($status === 'successful' && ($charge['paid'] ?? false)) {
            // จ่ายเป็นสกุลอื่นถือว่ายอดไม่ตรง ระบบจะส่งให้แอดมินตรวจแทนการยืนยันอัตโนมัติ
            $amount = strtoupper((string) ($charge['currency'] ?? 'THB')) === 'THB' ? ((int) $charge['amount']) / 100 : 0;

            $this->recordSuccess($reservation, Payment::PROVIDER_OMISE, $amount, $chargeId, null, null, $this->omiseSummary($charge));

            return;
        }

        if (in_array($status, ['failed', 'expired', 'reversed'], true)) {
            $message = $status === 'expired'
                ? 'QR หมดอายุ'
                : app(OmiseGateway::class)->failureMessage($charge);

            $this->recordFailure(Payment::PROVIDER_OMISE, $chargeId, $message, $this->omiseSummary($charge));
        }
    }

    /** Omise ยืนยันการคืนเงิน: ปิดรายการคืนเงินที่ยังค้างอยู่ของ charge นั้น */
    public function syncOmiseRefund(string $chargeId, string $refundId): void
    {
        if (! str_starts_with($chargeId, 'chrg_') || ! str_starts_with($refundId, 'rfnd_')) {
            return;
        }

        $refund = Refund::query()
            ->where('provider_refund_id', $refundId)
            ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Failed->value])
            ->first();

        if (! $refund) {
            return;
        }

        $remote = app(\App\Services\Payments\Omise\OmiseClient::class)->retrieveRefund($chargeId, $refundId);

        if (($remote['charge'] ?? null) === $chargeId) {
            $this->settleRefund($refund, RefundStatus::Succeeded, null, null, $refundId);
        }
    }

    /**
     * เก็บเฉพาะข้อมูลที่ต้องใช้ ไม่เก็บ payload ทั้งก้อน
     * ชื่อเจ้าของบัตรและที่อยู่ไม่ใช่ธุระของเรา บัตรเก็บแค่ยี่ห้อกับเลขท้ายไว้ให้ผู้ชำระจำได้
     */
    protected function omiseSummary(array $charge): array
    {
        return array_filter([
            'id' => $charge['id'] ?? null,
            'status' => $charge['status'] ?? null,
            'amount' => $charge['amount'] ?? null,
            'currency' => $charge['currency'] ?? null,
            'paid_at' => $charge['paid_at'] ?? null,
            'failure_code' => $charge['failure_code'] ?? null,
            'livemode' => $charge['livemode'] ?? null,
            'source_type' => data_get($charge, 'source.type'),
            'method' => isset($charge['card']) ? Payment::METHOD_CARD : data_get($charge, 'source.type'),
            'card' => isset($charge['card']) ? array_filter([
                'brand' => data_get($charge, 'card.brand'),
                'last_digits' => data_get($charge, 'card.last_digits'),
            ]) : null,
        ], fn ($v) => $v !== null);
    }
}
