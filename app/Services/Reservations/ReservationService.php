<?php

namespace App\Services\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Models\SlotLock;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ตรรกะการจอง Private Gym ทั้งหมด หน้าเว็บ หลังบ้าน และ job ต้องเรียกผ่านคลาสนี้
 *
 * กติกาที่ห้ามพัง: ทุกครั้งที่สร้าง ย้าย หรือเปลี่ยนคนในการจอง ต้องเขียน slot_locks
 * ภายใน transaction เดียวกับตัวการจอง ถ้าล็อกชนกับคนอื่น ทั้งรายการต้องย้อนกลับ
 */
class ReservationService
{
    public function __construct(
        protected Availability $availability,
        protected OpeningHours $hours,
        protected ReservationLocker $locker,
        protected ReservationNotifier $notify,
    ) {}

    // ------------------------------------------------------------------
    // สร้างการจอง
    // ------------------------------------------------------------------

    /**
     * สร้างการจองแล้วกันช่วงเวลาไว้รอชำระเงิน
     *
     * @param  array<int, int>  $memberIds  ผู้เข้าร่วมทั้งหมด ไม่รวม Trainer
     */
    public function create(
        Branch $branch,
        CarbonInterface $start,
        int $hours,
        array $memberIds,
        int $payerMemberId,
        ?Trainer $trainer,
        User $actor,
        string $via,
    ): Reservation {
        $start = CarbonImmutable::parse($start)->second(0);

        $this->assertTiming($branch, $start, $hours);
        $members = $this->participants($branch, $memberIds);

        if (! $members->contains('id', $payerMemberId)) {
            throw ReservationException::payerNotInGroup();
        }

        if ($via === Reservation::VIA_TRAINEE && ! $members->contains('id', $actor->member?->id)) {
            throw ReservationException::bookerNotInGroup();
        }

        // Trainer สร้างการจองได้ แต่ระบบกำหนดให้ผู้จองเป็น Trainer ประจำการจองเสมอ
        if ($via === Reservation::VIA_TRAINER && $trainer?->id !== $actor->trainer?->id) {
            throw ReservationException::notAllowed();
        }

        if ($trainer === null && ! $this->mayGoWithoutTrainer($members)) {
            throw ReservationException::trainerRequired();
        }

        if ($trainer && $trainer->branch_id !== $branch->id) {
            throw ReservationException::trainerUnavailable();
        }

        $this->assertResourcesFree($branch, $start, $hours, $trainer, $members);

        $holdUntil = now()->addMinutes((int) config('gym.reservation.hold_minutes', 30));

        if (config('gym.reservation.hold_ends_at_start', true) && $start->lt($holdUntil)) {
            $holdUntil = $start;
        }

        try {
            $reservation = DB::transaction(function () use ($branch, $start, $hours, $members, $payerMemberId, $trainer, $actor, $via, $holdUntil) {
                $this->releaseStaleHolds($branch, $start, $hours, $trainer, $members);

                $reservation = Reservation::create([
                    'branch_id' => $branch->id,
                    'trainer_id' => $trainer?->id,
                    'payer_member_id' => $payerMemberId,
                    'created_by_user_id' => $actor->id,
                    'created_via' => $via,
                    'starts_at' => $start,
                    'ends_at' => $start->addHours($hours),
                    'hours' => $hours,
                    'status' => ReservationStatus::PendingPayment,
                    'hold_expires_at' => $holdUntil,
                    'hourly_rate' => $branch->hourly_rate,
                    'amount' => bcmul((string) $branch->hourly_rate, (string) $hours, 2),
                    'currency' => 'THB',
                ]);

                $reservation->participants()->attach($members->pluck('id'));
                $this->locker->lock($reservation->setRelation('participants', $members));

                return $reservation;
            });
        } catch (QueryException $e) {
            throw $this->explainConflict($e, $branch, $start, $hours, $trainer, $members);
        }

        $reservation->load('participants.user', 'trainer.user', 'payer.user');

        $this->notify->send(
            $reservation,
            $this->notify->payer($reservation),
            'มีรายการรอชำระเงิน',
            'การจอง '.$reservation->dateLabel().' '.$reservation->timeLabel()
                .' ยอด '.number_format((float) $reservation->amount, 2).' บาท'
                .' กรุณาชำระภายใน '.$reservation->hold_expires_at->format('H:i').' น.',
            $this->notify->urlFor($reservation),
        );

        return $reservation;
    }

    // ------------------------------------------------------------------
    // หมดเวลาชำระ
    // ------------------------------------------------------------------

    /** ปิดรายการรอชำระที่เลยเวลา แล้วคืนช่วงเวลาทั้งหมด */
    public function expireStaleHolds(): int
    {
        $stale = Reservation::query()
            ->where('status', ReservationStatus::PendingPayment->value)
            ->where('hold_expires_at', '<=', now())
            ->pluck('id');

        $count = 0;

        foreach ($stale as $id) {
            if ($this->expire($id)) {
                $count++;
            }
        }

        return $count;
    }

    protected function expire(int $reservationId, bool $notify = true): bool
    {
        $reservation = DB::transaction(function () use ($reservationId) {
            $r = Reservation::whereKey($reservationId)->lockForUpdate()->first();

            if (! $r || $r->status !== ReservationStatus::PendingPayment || $r->hold_expires_at->isFuture()) {
                return null;
            }

            $r->update(['status' => ReservationStatus::Expired, 'expired_at' => now()]);
            $this->locker->release($r);

            return $r;
        });

        if ($reservation && $notify) {
            $this->notify->send(
                $reservation,
                $this->notify->payer($reservation)->push($reservation->createdBy)->filter(),
                'รายการจองหมดอายุ',
                'ไม่ได้รับการชำระเงินภายในเวลาที่กำหนด ช่วงเวลา '.$reservation->dateLabel().' '.$reservation->timeLabel().' ถูกปล่อยให้ผู้อื่นจองแล้ว',
                $this->notify->urlFor($reservation),
            );
        }

        return $reservation !== null;
    }

    /**
     * รายการรอชำระที่หมดเวลาแล้วแต่ยังถือล็อกช่วงที่กำลังจะจอง
     * ปล่อยก่อนใส่ล็อกใหม่ ไม่ต้องรอ job รอบถัดไป
     */
    protected function releaseStaleHolds(Branch $branch, CarbonImmutable $start, int $hours, ?Trainer $trainer, Collection $members): void
    {
        $end = $start->addHours($hours);

        $ids = SlotLock::query()
            ->where('slot_start', '>=', $start)
            ->where('slot_start', '<', $end)
            ->where(function ($q) use ($branch, $trainer, $members) {
                $q->where(fn ($q) => $q->where('resource', SlotLock::GYM)->where('resource_id', $branch->id))
                    ->orWhere(fn ($q) => $q->where('resource', SlotLock::MEMBER)->whereIn('resource_id', $members->pluck('id')));

                if ($trainer) {
                    $q->orWhere(fn ($q) => $q->where('resource', SlotLock::TRAINER)->where('resource_id', $trainer->id));
                }
            })
            ->whereHas('reservation', fn ($q) => $q
                ->where('status', ReservationStatus::PendingPayment->value)
                ->where('hold_expires_at', '<=', now()))
            ->distinct()
            ->pluck('reservation_id');

        foreach ($ids as $id) {
            $this->expire($id);
        }
    }

    // ------------------------------------------------------------------
    // เลื่อนวันเวลา
    // ------------------------------------------------------------------

    /**
     * เลื่อนการจองที่ยืนยันแล้ว คงราคาและยอดชำระเดิม ความยาวเท่าเดิม
     * ก่อน 00.00 น. ของวันใช้งานเลื่อนเองได้ หลังจากนั้นต้องผ่านการอนุมัติ ($approved)
     */
    public function reschedule(Reservation $reservation, CarbonInterface $newStart, User $actor, ?string $reason = null, bool $approved = false): Reservation
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            throw ReservationException::notConfirmed();
        }

        $this->assertCanManage($reservation, $actor);

        if (! $approved && ! $this->isStaff($actor) && ! $reservation->canRescheduleWithoutApproval()) {
            throw ReservationException::needsApproval();
        }

        $newStart = CarbonImmutable::parse($newStart)->second(0);
        $branch = $reservation->branch;
        $members = $reservation->participants()->with('user')->get();
        $trainer = $reservation->trainer;

        $this->assertTiming($branch, $newStart, $reservation->hours, checkPrice: false);
        $this->assertResourcesFree($branch, $newStart, $reservation->hours, $trainer, $members, $reservation->id);

        $before = $this->snapshot($reservation);

        try {
            DB::transaction(function () use ($reservation, $newStart) {
                $this->locker->release($reservation);

                $reservation->update([
                    'starts_at' => $newStart,
                    'ends_at' => $newStart->addHours($reservation->hours),
                ]);

                $this->locker->lock($reservation->refresh());
            });
        } catch (QueryException $e) {
            throw $this->explainConflict($e, $branch, $newStart, $reservation->hours, $trainer, $members, $reservation->id);
        }

        $reservation->refresh();

        AuditLog::record('reservation.rescheduled', $reservation, $before, $this->snapshot($reservation), $reason, $actor);

        $this->notify->send(
            $reservation,
            $this->notify->everyone($reservation),
            'เปลี่ยนวันเวลาการจองแล้ว',
            'จาก '.$before['when'].' เป็น '.$reservation->dateLabel().' '.$reservation->timeLabel(),
            $this->notify->urlFor($reservation),
        );

        return $reservation;
    }

    /** หลังเส้นตาย: ส่งคำขอเลื่อนให้แอดมิน การจองเดิมยังคงอยู่ระหว่างรอ */
    public function requestReschedule(Reservation $reservation, CarbonInterface $newStart, User $actor, string $reason): ReservationRequest
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            throw ReservationException::notConfirmed();
        }

        $this->assertCanManage($reservation, $actor);
        $this->assertNoPendingRequest($reservation, RequestType::Reschedule);

        $newStart = CarbonImmutable::parse($newStart)->second(0);
        $branch = $reservation->branch;
        $members = $reservation->participants()->with('user')->get();

        // ตรวจตอนขอด้วย ไม่ให้แอดมินต้องมาเจอคำขอที่เป็นไปไม่ได้ตั้งแต่แรก
        // และจะตรวจซ้ำอีกครั้งตอนอนุมัติ เพราะระหว่างรออาจมีคนจองไปแล้ว
        $this->assertTiming($branch, $newStart, $reservation->hours, checkPrice: false);
        $this->assertResourcesFree($branch, $newStart, $reservation->hours, $reservation->trainer, $members, $reservation->id);

        $request = $reservation->requests()->create([
            'type' => RequestType::Reschedule,
            'requested_by_user_id' => $actor->id,
            'reason' => $reason,
            'new_starts_at' => $newStart,
            'new_ends_at' => $newStart->addHours($reservation->hours),
            'status' => RequestStatus::Pending,
        ]);

        $this->notify->send(
            $reservation,
            $this->notify->admins($branch),
            'มีคำขอเลื่อนการจอง',
            $actor->name.' ขอเลื่อน '.$reservation->reference.' เป็น '
                .$newStart->locale('th')->isoFormat('ddd D MMM').' '.$newStart->format('H:i').' — '.$reason,
            url('/admin/reservation-requests'),
        );

        return $request;
    }

    // ------------------------------------------------------------------
    // Trainer ขอยกเลิกการรับงาน และการเปลี่ยน Trainer
    // ------------------------------------------------------------------

    /** Trainer ยกเลิกการจองของลูกค้าเองไม่ได้ ส่งคำขอให้แอดมินหาคนแทนได้เท่านั้น */
    public function requestTrainerWithdrawal(Reservation $reservation, User $actor, string $reason): ReservationRequest
    {
        if (! $reservation->status->holdsSlots()) {
            throw ReservationException::notConfirmed();
        }

        if ($reservation->trainer_id === null || $actor->trainer?->id !== $reservation->trainer_id) {
            throw ReservationException::notAllowed();
        }

        $this->assertNoPendingRequest($reservation, RequestType::TrainerWithdrawal);

        $request = $reservation->requests()->create([
            'type' => RequestType::TrainerWithdrawal,
            'requested_by_user_id' => $actor->id,
            'reason' => $reason,
            'status' => RequestStatus::Pending,
        ]);

        $this->notify->send(
            $reservation,
            $this->notify->admins($reservation->branch),
            'Trainer ขอยกเลิกการรับงาน',
            $actor->name.' ขอยกเลิกงาน '.$reservation->reference.' '.$reservation->dateLabel().' '.$reservation->timeLabel().' — '.$reason,
            url('/admin/reservation-requests'),
        );

        return $request;
    }

    /** ย้ายงานไปยัง Trainer คนใหม่ที่ว่างครบทุกชั่วโมง ยิม ผู้เข้าร่วม และยอดชำระคงเดิม */
    public function changeTrainer(Reservation $reservation, Trainer $newTrainer, User $actor, ?string $reason = null): Reservation
    {
        if (! $reservation->status->holdsSlots()) {
            throw ReservationException::notConfirmed();
        }

        if ($newTrainer->id === $reservation->trainer_id) {
            return $reservation;
        }

        if ($newTrainer->branch_id !== $reservation->branch_id
            || ! $this->availability->trainerCovers($newTrainer, $reservation->starts_at, $reservation->hours)) {
            throw ReservationException::trainerUnavailable();
        }

        $oldTrainer = $reservation->trainer;
        $before = $this->snapshot($reservation);

        try {
            DB::transaction(function () use ($reservation, $newTrainer) {
                $this->locker->release($reservation, SlotLock::TRAINER);
                $reservation->update(['trainer_id' => $newTrainer->id]);
                $this->locker->lockTrainer($reservation, $newTrainer->id);
            });
        } catch (QueryException $e) {
            if (ReservationLocker::isDuplicate($e)) {
                throw ReservationException::trainerUnavailable();
            }

            throw $e;
        }

        $reservation->refresh();

        AuditLog::record('reservation.trainer_changed', $reservation, $before, $this->snapshot($reservation), $reason, $actor);

        $this->notify->send(
            $reservation,
            $this->notify->everyone($reservation)->push($oldTrainer?->user)->filter(),
            'เปลี่ยน Trainer แล้ว',
            'การจอง '.$reservation->dateLabel().' '.$reservation->timeLabel()
                .' เปลี่ยน Trainer จาก '.($oldTrainer?->user?->name ?? 'ไม่มี').' เป็น '.$newTrainer->user->name,
            $this->notify->urlFor($reservation),
        );

        return $reservation;
    }

    // ------------------------------------------------------------------
    // แอดมินตัดสินคำขอ
    // ------------------------------------------------------------------

    public function approveRequest(ReservationRequest $request, User $admin, ?Trainer $replacement = null, ?string $note = null): ReservationRequest
    {
        if ($request->status !== RequestStatus::Pending) {
            throw new ReservationException('คำขอนี้ถูกพิจารณาไปแล้ว', 'request_closed');
        }

        $reservation = $request->reservation;

        if ($request->type === RequestType::Reschedule) {
            // ตรวจความว่างของเวลาใหม่อีกครั้งในตัว reschedule
            $this->reschedule($reservation, $request->new_starts_at, $admin, $request->reason, approved: true);
        } else {
            if (! $replacement) {
                throw new ReservationException('ต้องเลือก Trainer ที่จะมาแทน', 'replacement_required');
            }

            $this->changeTrainer($reservation, $replacement, $admin, 'แทน Trainer ที่ขอยกเลิกการรับงาน: '.$request->reason);
        }

        $request->update([
            'status' => RequestStatus::Approved,
            'replacement_trainer_id' => $replacement?->id,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        AuditLog::record('request.approved', $reservation, null, [
            'request_id' => $request->id,
            'type' => $request->type->value,
        ], $note, $admin);

        $this->notify->send(
            $reservation,
            collect([$request->requestedBy]),
            'อนุมัติคำขอแล้ว',
            $request->type->label().' ของการจอง '.$reservation->reference.' ได้รับการอนุมัติ',
            $this->notify->urlFor($reservation),
        );

        return $request->refresh();
    }

    public function rejectRequest(ReservationRequest $request, User $admin, string $note): ReservationRequest
    {
        if ($request->status !== RequestStatus::Pending) {
            throw new ReservationException('คำขอนี้ถูกพิจารณาไปแล้ว', 'request_closed');
        }

        $request->update([
            'status' => RequestStatus::Rejected,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        AuditLog::record('request.rejected', $request->reservation, null, [
            'request_id' => $request->id,
            'type' => $request->type->value,
        ], $note, $admin);

        $this->notify->send(
            $request->reservation,
            collect([$request->requestedBy]),
            'ไม่อนุมัติคำขอ',
            $request->type->label().' ของการจอง '.$request->reservation->reference.' ไม่ได้รับการอนุมัติ: '.$note,
            $this->notify->urlFor($request->reservation),
        );

        return $request->refresh();
    }

    // ------------------------------------------------------------------
    // ยกเลิกโดยแอดมิน (กรณีให้บริการไม่ได้) พร้อมคืนเงินเต็มจำนวน
    // ------------------------------------------------------------------

    public function cancelByAdmin(Reservation $reservation, User $admin, string $reason): Reservation
    {
        if (! $reservation->status->holdsSlots()) {
            throw new ReservationException('การจองนี้ไม่ได้อยู่ในสถานะที่ยกเลิกได้', 'not_cancellable');
        }

        $before = $this->snapshot($reservation);

        DB::transaction(function () use ($reservation, $admin, $reason) {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $admin->id,
                'cancellation_reason' => $reason,
                'hold_expires_at' => null,
            ]);

            $this->locker->release($reservation);

            // ปิดคำขอที่ค้างอยู่ทั้งหมด เพราะการจองไม่มีแล้ว
            $reservation->requests()->where('status', RequestStatus::Pending->value)
                ->update(['status' => RequestStatus::Cancelled->value]);
        });

        $reservation->refresh();

        AuditLog::record('reservation.cancelled', $reservation, $before, $this->snapshot($reservation), $reason, $admin);

        $payment = $reservation->payments()->where('status', PaymentStatus::Succeeded->value)->latest('id')->first();

        if ($payment) {
            app(PaymentService::class)->refundInFull($reservation, $payment, $admin, $reason);
        }

        $this->notify->send(
            $reservation,
            $this->notify->everyone($reservation),
            'ยกเลิกการจอง',
            'การจอง '.$reservation->dateLabel().' '.$reservation->timeLabel().' ถูกยกเลิก: '.$reason
                .($payment ? ' ระบบกำลังคืนเงินเต็มจำนวนให้ผู้ชำระเงิน' : ''),
            $this->notify->urlFor($reservation),
        );

        return $reservation->refresh();
    }

    // ------------------------------------------------------------------
    // กติกา
    // ------------------------------------------------------------------

    public function assertTiming(Branch $branch, CarbonImmutable $start, int $hours, bool $checkPrice = true): void
    {
        $max = max(1, (int) $branch->max_booking_hours);

        if ($hours < 1 || $hours > $max) {
            throw ReservationException::invalidDuration($max);
        }

        if ($start->lte(now())) {
            throw ReservationException::inThePast();
        }

        $window = (int) $branch->booking_window_days;

        if ($start->gt(now()->addDays($window)->endOfDay())) {
            throw ReservationException::beyondWindow($window);
        }

        if (! $this->availability->gymOpen($branch, $start, $hours)) {
            throw ReservationException::gymClosed();
        }

        // ยังไม่ตั้งราคา = ห้ามเปิดจอง กันแอดมินลืมแล้วกลายเป็นจองฟรี
        if ($checkPrice && (float) $branch->hourly_rate <= 0) {
            throw new ReservationException('สาขายังไม่ได้ตั้งราคาต่อชั่วโมง ติดต่อแอดมิน', 'price_not_set');
        }
    }

    /** @return Collection<int, Member> */
    public function participants(Branch $branch, array $memberIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $memberIds)));
        $max = max(1, (int) $branch->max_trainees);

        if (count($ids) < 1 || count($ids) > $max) {
            throw ReservationException::groupSize($max);
        }

        $members = Member::with('user')->whereKey($ids)->get();

        foreach ($ids as $id) {
            $member = $members->firstWhere('id', $id);

            if (! $member) {
                throw new ReservationException('ไม่พบสมาชิกที่ระบุ', 'member_not_found');
            }

            if ($member->branch_id !== $branch->id || ! $member->canJoinReservations()) {
                throw ReservationException::memberNotEligible($member->user?->name ?? $member->member_code);
            }
        }

        return $members;
    }

    /** ข้อ 13 ยังไม่ยืนยัน ค่าเริ่มต้นตามข้อเสนอในเอกสาร: ทุกคนต้องมีสิทธิ์ */
    public function mayGoWithoutTrainer(Collection $members): bool
    {
        return config('gym.reservation.no_trainer_rule', 'all') === 'any'
            ? $members->contains(fn (Member $m) => $m->can_book_without_trainer)
            : $members->every(fn (Member $m) => $m->can_book_without_trainer);
    }

    protected function assertResourcesFree(Branch $branch, CarbonImmutable $start, int $hours, ?Trainer $trainer, Collection $members, ?int $ignore = null): void
    {
        if (! $this->availability->gymFree($branch, $start, $hours, $ignore)) {
            throw ReservationException::gymTaken();
        }

        if ($trainer && ! $this->availability->trainerCovers($trainer, $start, $hours, $ignore)) {
            throw ReservationException::trainerUnavailable();
        }

        foreach ($members as $member) {
            if (! $this->availability->memberFree($member, $start, $hours, $ignore)) {
                throw ReservationException::memberBusy($member->user?->name ?? $member->member_code);
            }
        }
    }

    /** ล็อกชนตอนบันทึก (มีคนจองตัดหน้าในเสี้ยววินาที) แปลงเป็นเหตุผลที่อ่านเข้าใจ */
    protected function explainConflict(QueryException $e, Branch $branch, CarbonImmutable $start, int $hours, ?Trainer $trainer, Collection $members, ?int $ignore = null): \Throwable
    {
        if (! ReservationLocker::isDuplicate($e)) {
            return $e;
        }

        try {
            $this->assertResourcesFree($branch, $start, $hours, $trainer, $members, $ignore);
        } catch (ReservationException $friendly) {
            return $friendly;
        }

        return ReservationException::gymTaken();
    }

    protected function assertCanManage(Reservation $reservation, User $actor): void
    {
        $allowed = $this->isStaff($actor)
            || ($reservation->trainer_id && $actor->trainer?->id === $reservation->trainer_id)
            || ($actor->member && $actor->member->id === $reservation->payer_member_id)
            || $actor->id === $reservation->created_by_user_id;

        if (! $allowed) {
            throw ReservationException::notAllowed();
        }
    }

    protected function assertNoPendingRequest(Reservation $reservation, RequestType $type): void
    {
        $exists = $reservation->requests()
            ->where('type', $type->value)
            ->where('status', RequestStatus::Pending->value)
            ->exists();

        if ($exists) {
            throw ReservationException::requestPending();
        }
    }

    public function isStaff(User $user): bool
    {
        return (bool) $user->role?->canAccessAdminPanel();
    }

    protected function snapshot(Reservation $reservation): array
    {
        $reservation->loadMissing('trainer.user');

        return [
            'starts_at' => $reservation->starts_at->toDateTimeString(),
            'ends_at' => $reservation->ends_at->toDateTimeString(),
            'when' => $reservation->dateLabel().' '.$reservation->timeLabel(),
            'trainer_id' => $reservation->trainer_id,
            'trainer' => $reservation->trainer?->user?->name,
            'status' => $reservation->status->value,
        ];
    }
}
