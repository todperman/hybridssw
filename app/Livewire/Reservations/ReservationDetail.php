<?php

namespace App\Livewire\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\PaymentGateways;
use App\Services\Payments\PaymentService;
use App\Services\Reservations\Availability;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * หน้าการจองหนึ่งรายการ เห็นได้เฉพาะผู้เกี่ยวข้อง
 *
 * รอชำระ: นับถอยหลัง แสดงวิธีชำระ (QR ของ Omise หรือคำแนะนำโอนให้แอดมินตรวจ) และ poll สถานะ
 * ยืนยันแล้ว: เลื่อนเองได้ก่อน 00.00 น. ของวันใช้งาน หลังจากนั้นส่งคำขอให้แอดมิน
 * Trainer ประจำงานส่งคำขอยกเลิกการรับงานได้ แต่ยกเลิกเองไม่ได้
 */
class ReservationDetail extends Component
{
    #[Locked]
    public string $reference = '';

    /** @var array{charge_id?: string, qr?: string}|null */
    public ?array $checkout = null;

    public ?string $flash = null;
    public ?string $error = null;

    // เลื่อนวันเวลา
    public bool $rescheduling = false;
    public string $newDate = '';
    public ?string $newStart = null;
    public string $rescheduleReason = '';

    // Trainer ขอยกเลิกการรับงาน
    public bool $withdrawing = false;
    public string $withdrawReason = '';

    #[Locked]
    public int $lastSyncAt = 0;

    public function mount(string $reference): void
    {
        $reservation = Reservation::where('reference', $reference)->firstOrFail();

        abort_unless($reservation->isVisibleTo(auth()->user()), 404);

        $this->reference = $reference;
        $this->newDate = $reservation->starts_at->toDateString();
    }

    #[Computed]
    public function reservation(): Reservation
    {
        return Reservation::where('reference', $this->reference)
            ->with(['branch', 'trainer.user', 'payer.user', 'participants.user', 'createdBy', 'payments', 'refunds', 'requests.requestedBy'])
            ->firstOrFail();
    }

    #[Computed]
    public function isPayer(): bool
    {
        return auth()->user()->member?->id === $this->reservation->payer_member_id;
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->reservation->isManageableBy(auth()->user());
    }

    #[Computed]
    public function isAssignedTrainer(): bool
    {
        $trainerId = $this->reservation->trainer_id;

        return $trainerId !== null && auth()->user()->trainer?->id === $trainerId;
    }

    #[Computed]
    public function onlinePayment(): bool
    {
        return app(PaymentGateways::class)->current()->isOnline();
    }

    /** เวลาเริ่มที่เลื่อนไปได้ในวันที่เลือก ใช้ Trainer คนเดิมและความยาวเท่าเดิม */
    #[Computed]
    public function rescheduleTimes(): array
    {
        $r = $this->reservation;
        $availability = app(Availability::class);

        $times = $availability->gymStartTimes($r->branch, CarbonImmutable::parse($this->newDate), $r->hours, $r->id);

        if ($r->trainer) {
            $times = array_filter($times, fn ($t) => $availability->trainerCovers($r->trainer, $t, $r->hours, $r->id));
        }

        return array_values(array_filter($times, fn ($t) => ! $t->equalTo($r->starts_at)));
    }

    #[Computed]
    public function rescheduleDays(): array
    {
        $today = CarbonImmutable::today();

        return array_map(fn ($i) => $today->addDays($i), range(0, (int) $this->reservation->branch->booking_window_days));
    }

    /** wire:poll ขณะรอชำระ ปิดรายการที่หมดเวลา และถามผลจาก Omise ถ้า webhook ยังไม่มา */
    public function refreshStatus(ReservationService $reservations, PaymentService $payments): void
    {
        $r = $this->reservation;

        if ($r->status !== ReservationStatus::PendingPayment) {
            return;
        }

        if ($r->hold_expires_at->isPast()) {
            $reservations->expire($r->id);
            unset($this->reservation);

            return;
        }

        // ถาม Omise ไม่บ่อยกว่า 10 วินาทีต่อหน้า ไม่ให้ยิง API ถี่เกินจำเป็น
        $pending = $r->payments->first(fn (Payment $p) => $p->provider === Payment::PROVIDER_OMISE && $p->status === PaymentStatus::Pending);

        if ($pending && time() - $this->lastSyncAt >= 10) {
            $this->lastSyncAt = time();

            try {
                $payments->syncOmiseCharge($pending->provider_charge_id);
            } catch (\Throwable $e) {
                Log::warning('ตรวจสถานะ Omise ไม่สำเร็จ', ['reference' => $r->reference, 'error' => $e->getMessage()]);
            }

            unset($this->reservation);
        }
    }

    public function startPayment(PaymentGateways $gateways): void
    {
        $this->error = null;
        $r = $this->reservation;

        if (! $this->isPayer || $r->status !== ReservationStatus::PendingPayment || $r->hold_expires_at->isPast()) {
            return;
        }

        try {
            $this->checkout = $gateways->current()->startCheckout($r);
        } catch (\Throwable $e) {
            Log::error('เริ่มชำระเงินไม่สำเร็จ', ['reference' => $r->reference, 'error' => $e->getMessage()]);
            $this->error = 'เปิดหน้าชำระเงินไม่สำเร็จ ลองอีกครั้งในอีกสักครู่';
        }
    }

    public function openReschedule(): void
    {
        $this->rescheduling = true;
        $this->newStart = null;
        $this->error = null;
    }

    public function pickRescheduleDay(string $date): void
    {
        $this->newDate = $date;
        $this->newStart = null;
        unset($this->rescheduleTimes);
    }

    public function pickRescheduleStart(string $time): void
    {
        $this->newStart = $time;
    }

    public function submitReschedule(ReservationService $reservations): void
    {
        $this->error = null;
        $r = $this->reservation;
        $user = auth()->user();

        if (! $this->newStart) {
            $this->error = 'เลือกเวลาใหม่ก่อน';

            return;
        }

        $start = CarbonImmutable::parse($this->newDate.' '.$this->newStart);
        $direct = $r->canRescheduleWithoutApproval() || $reservations->isStaff($user);

        if (! $direct && blank($this->rescheduleReason)) {
            $this->error = 'เลยเวลาเลื่อนเองแล้ว กรุณาระบุเหตุผลเพื่อส่งให้แอดมินพิจารณา';

            return;
        }

        try {
            if ($direct) {
                $reservations->reschedule($r, $start, $user, $this->rescheduleReason ?: null);
                $this->flash = 'เลื่อนการจองแล้ว';
            } else {
                $reservations->requestReschedule($r, $start, $user, trim($this->rescheduleReason));
                $this->flash = 'ส่งคำขอเลื่อนให้แอดมินแล้ว การจองเดิมยังคงอยู่ระหว่างรอ';
            }
        } catch (ReservationException $e) {
            $this->error = $e->getMessage();
            unset($this->rescheduleTimes);

            return;
        }

        $this->rescheduling = false;
        $this->rescheduleReason = '';
        unset($this->reservation);
    }

    public function submitWithdrawal(ReservationService $reservations): void
    {
        $this->error = null;

        $this->validate(['withdrawReason' => 'required|string|max:500'], [], ['withdrawReason' => 'เหตุผล']);

        try {
            $reservations->requestTrainerWithdrawal($this->reservation, auth()->user(), trim($this->withdrawReason));
        } catch (ReservationException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->withdrawing = false;
        $this->withdrawReason = '';
        $this->flash = 'ส่งคำขอแล้ว แอดมินจะจัด Trainer คนอื่นมาแทน งานนี้ยังเป็นของคุณจนกว่าแอดมินจะอนุมัติ';
        unset($this->reservation);
    }

    public function render()
    {
        return view('livewire.reservations.reservation-detail')->layout('layouts.app');
    }
}
