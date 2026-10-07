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
        if (($event['key'] ?? null) !== 'charge.complete') {
            return;
        }

        $chargeId = data_get($event, 'data.id');

        if ($chargeId) {
            $this->syncOmiseCharge($chargeId);
        }
    }

    /**
     * ดึงสถานะ charge จาก Omise แล้วบันทึกผล ใช้ทั้งจาก webhook และหน้าการจองที่รอผลอยู่
     * เผื่อ webhook มาช้าหรือไม่มาเลย ผู้จ่ายจะได้เห็นผลทันทีที่จ่ายเสร็จ
     */
    public function syncOmiseCharge(string $chargeId): void
    {
        $charge = app(OmiseGateway::class)->fetchCharge($chargeId);
        $reference = data_get($charge, 'metadata.reservation');
        $reservation = $reference ? Reservation::where('reference', $reference)->first() : null;

        if (! $reservation) {
            return;
        }

        if (($charge['status'] ?? null) === 'successful' && ($charge['paid'] ?? false)) {
            $this->recordSuccess($reservation, Payment::PROVIDER_OMISE, ((int) $charge['amount']) / 100, $chargeId, null, null, $charge);

            return;
        }

        if (in_array($charge['status'] ?? null, ['failed', 'expired', 'reversed'], true)) {
            $this->recordFailure(Payment::PROVIDER_OMISE, $chargeId, $charge['failure_message'] ?? $charge['status'], $charge);
        }
    }
}
