<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\MemberStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Member;
use App\Models\MemberPackage;
use App\Models\Trainer;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * ตรรกะการจองทั้งหมดอยู่ที่นี่ที่เดียว หน้าเว็บ หลังบ้าน และ job ต้องเรียกผ่านคลาสนี้
 *
 * กติกาความถูกต้องที่ห้ามพัง:
 * ทุกครั้งที่อ่านหรือแก้จำนวนที่นั่ง ต้อง lockForUpdate แถว workout_sessions ก่อนเสมอ
 * ไม่งั้นสองคำขอที่เข้ามาพร้อมกันจะเห็นที่นั่งว่างใบเดียวกันแล้วจองทะลุ capacity
 */
class BookingService
{
    /**
     * เทรนเนอร์จองที่นั่งให้ลูกทีมหนึ่งคน
     * ถ้าเต็มและ $allowWaitlist เป็นจริงจะได้คิวสำรองแทน
     */
    public function book(
        WorkoutSession $session,
        Member $member,
        Trainer $trainer,
        ?User $actor = null,
        bool $allowWaitlist = true,
        bool $requireCredit = true,
    ): Booking {
        return DB::transaction(function () use ($session, $member, $trainer, $actor, $allowWaitlist, $requireCredit) {
            // ล็อกรอบก่อนตัดสินใจเรื่องที่นั่ง แล้วอ่านค่าล่าสุดจาก DB ไม่ใช่จากอ็อบเจ็กต์ที่ส่งเข้ามา
            $session = WorkoutSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            $this->assertTrainerCanBook($session, $trainer);
            $this->assertMemberCanBook($session, $member, $trainer);

            $isWaitlist = $session->booked_count >= $session->capacity;

            if ($isWaitlist && ! $allowWaitlist) {
                throw BookingException::sessionFull();
            }

            // โควตาที่นั่งต่อรอบของเทรนเนอร์ นับเฉพาะที่นั่งจริงไม่รวมคิวสำรอง
            if (! $isWaitlist) {
                $quota = $trainer->maxSeatsPerSession();
                $used = $session->activeBookings()->where('trainer_id', $trainer->id)->count();

                if ($used >= $quota) {
                    throw BookingException::trainerSeatQuotaReached($quota);
                }
            }

            $package = null;

            if (! $isWaitlist && $requireCredit) {
                $package = $this->consumeCredit($member);
            }

            $booking = $this->createBooking($session, $member, $trainer, $actor, $isWaitlist, $package);

            if ($isWaitlist) {
                $session->increment('waitlist_count');
            } else {
                $session->increment('booked_count');
                $this->claimIfExclusive($session, $trainer);
            }

            return $booking->fresh(['workoutSession', 'member', 'trainer']);
        });
    }

    /**
     * จองให้ลูกทีมหลายคนในคำสั่งเดียว
     * ทำทีละคนเพื่อให้คนที่ผ่านเกณฑ์ได้ที่นั่ง ส่วนคนที่ติดปัญหาคืนเหตุผลกลับไป
     *
     * @param  array<int, Member>  $members
     * @return array{booked: array<int, Booking>, failed: array<int, array{member: Member, reason: string}>}
     */
    public function bookMany(
        WorkoutSession $session,
        array $members,
        Trainer $trainer,
        ?User $actor = null,
        bool $allowWaitlist = true,
        bool $requireCredit = true,
    ): array {
        $booked = [];
        $failed = [];

        foreach ($members as $member) {
            try {
                $booked[] = $this->book($session, $member, $trainer, $actor, $allowWaitlist, $requireCredit);
            } catch (BookingException $e) {
                $failed[] = ['member' => $member, 'reason' => $e->getMessage()];
            }
        }

        return ['booked' => $booked, 'failed' => $failed];
    }

    /**
     * ยกเลิกการจอง คืนเครดิตให้เมื่อยกเลิกก่อนเวลาตัดรอบ
     * ถ้าที่นั่งว่างลงจะเลื่อนคิวสำรองคนแรกขึ้นมาทันที
     */
    public function cancel(Booking $booking, ?User $actor = null, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $session = WorkoutSession::whereKey($booking->workout_session_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $booking->status->isCancellable()) {
                throw BookingException::notCancellable();
            }

            $wasSeated = $booking->status === BookingStatus::Booked;
            $isLate = now()->diffInMinutes($session->starts_at, false) < $session->branch->cancellation_cutoff_hours * 60;

            // ยกเลิกทันเวลาได้เครดิตคืน ยกเลิกกระชั้นถือว่าใช้ไปแล้ว
            if ($booking->credit_consumed && ! $isLate) {
                $this->refundCredit($booking);
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'active_member_key' => null,
                'waitlist_position' => null,
                'confirm_deadline_at' => null,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor?->id,
                'cancellation_reason' => $reason,
                'cancelled_late' => $isLate,
            ]);

            if ($wasSeated) {
                $session->decrement('booked_count');
                $this->releaseExclusiveClaim($session, $booking);
                $this->promoteFromWaitlist($session);
            } else {
                $session->decrement('waitlist_count');
                $this->resequenceWaitlist($session);
            }

            return $booking->fresh();
        });
    }

    /**
     * เลื่อนคิวสำรองคนแรกขึ้นเป็นที่นั่งจริง
     * ให้เวลายืนยันตามค่าของสาขา ถ้าไม่ยืนยันทันจะถูกปล่อยโดย job แล้วเลื่อนคนถัดไป
     *
     * เรียกได้เฉพาะตอนที่ถือ lock แถว session อยู่แล้วเท่านั้น
     */
    public function promoteFromWaitlist(WorkoutSession $session): ?Booking
    {
        if ($session->booked_count >= $session->capacity) {
            return null;
        }

        $next = $session->bookings()
            ->where('status', BookingStatus::Waitlisted->value)
            ->orderByRaw('waitlist_position IS NULL, waitlist_position ASC')
            ->orderBy('id')
            ->first();

        if (! $next) {
            return null;
        }

        $package = null;

        try {
            $package = $this->consumeCredit($next->member);
        } catch (BookingException) {
            // เครดิตหมดระหว่างรอคิว ข้ามคนนี้ไปก่อนและปล่อยให้แอดมินตามเก็บ
            $next->update(['notes' => trim(($next->notes ?? '').' เลื่อนคิวไม่สำเร็จ: เครดิตไม่พอ')]);

            return null;
        }

        $next->update([
            'status' => BookingStatus::Booked,
            'active_member_key' => $next->member_id,
            'waitlist_position' => null,
            'promoted_at' => now(),
            'confirm_deadline_at' => now()->addMinutes($session->branch->waitlist_confirm_minutes),
            'member_package_id' => $package?->id,
            'credit_consumed' => $package !== null,
        ]);

        $session->increment('booked_count');
        $session->decrement('waitlist_count');
        $this->resequenceWaitlist($session);

        return $next->fresh();
    }

    /** ลูกทีมยืนยันว่าจะมาหลังได้เลื่อนคิว */
    public function confirmPromotion(Booking $booking): Booking
    {
        $booking->update(['confirm_deadline_at' => null]);

        return $booking->fresh();
    }

    /** เช็คอินหน้างาน ใช้กับการสแกน QR ของการจอง */
    public function checkIn(Booking $booking, ?User $actor = null): Booking
    {
        if (! in_array($booking->status, [BookingStatus::Booked, BookingStatus::CheckedIn], true)) {
            throw new BookingException('การจองนี้เช็คอินไม่ได้ (สถานะ: '.$booking->status->label().')', 'not_checkable');
        }

        if ($booking->status === BookingStatus::CheckedIn) {
            return $booking;
        }

        $booking->update([
            'status' => BookingStatus::CheckedIn,
            'checked_in_at' => now(),
            'checked_in_by' => $actor?->id,
            'confirm_deadline_at' => null,
        ]);

        return $booking->fresh();
    }

    /**
     * บันทึกว่าไม่มาตามนัด แล้วสะสมสถิติ
     * ครบตามจำนวนที่สาขากำหนดจะถูกระงับสิทธิ์จองอัตโนมัติ
     */
    public function markNoShow(Booking $booking, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $session = WorkoutSession::whereKey($booking->workout_session_id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== BookingStatus::Booked) {
                throw new BookingException('บันทึกไม่มาตามนัดได้เฉพาะรายการที่จองไว้', 'not_no_showable');
            }

            $booking->update([
                'status' => BookingStatus::NoShow,
                'active_member_key' => null,
                'confirm_deadline_at' => null,
                'cancelled_by_user_id' => $actor?->id,
            ]);

            // ที่นั่งต้องถูกคืนด้วย ไม่งั้น booked_count จะค้างสูงกว่าจำนวนคนจริง
            // แล้วรอบนั้นจะรับคนเพิ่มไม่ได้อีกเลยทั้งที่ยังว่าง
            $session->decrement('booked_count');
            $this->releaseExclusiveClaim($session, $booking);

            // เลื่อนคิวสำรองให้เฉพาะตอนที่รอบยังไม่เริ่ม
            // ถ้าบันทึกย้อนหลังหลังรอบจบแล้ว การดันคนเข้าไปไม่มีความหมาย
            if ($session->isBookable()) {
                $this->promoteFromWaitlist($session);
            }

            $member = Member::whereKey($booking->member_id)->lockForUpdate()->firstOrFail();
            $branch = $session->branch;

            // เริ่มนับรอบใหม่ทุกเดือน ไม่ให้สถิติเก่าตามหลอกหลอน
            if ($member->no_show_reset_at === null || $member->no_show_reset_at->isPast()) {
                $member->no_show_count = 0;
                $member->no_show_reset_at = now()->addMonth();
            }

            $member->no_show_count++;

            if ($member->no_show_count >= $branch->no_show_strike_limit) {
                $member->status = MemberStatus::Suspended;
                $member->suspended_until = now()->addDays($branch->no_show_suspension_days);
                $member->suspension_reason = "ไม่มาตามนัดครบ {$member->no_show_count} ครั้ง";
            }

            $member->save();

            return $booking->fresh();
        });
    }

    // --- กติกาการจอง ---

    protected function assertTrainerCanBook(WorkoutSession $session, Trainer $trainer): void
    {
        if (! $session->status->acceptsBookings()) {
            throw BookingException::sessionNotOpen();
        }

        if ($session->hasStarted()) {
            throw BookingException::sessionStarted();
        }

        if ($trainer->branch_id !== $session->branch_id) {
            throw BookingException::branchMismatch();
        }

        if (! $trainer->isApproved()) {
            throw BookingException::trainerNotApproved();
        }

        if (! $trainer->isContractActive()) {
            throw BookingException::trainerContractInactive();
        }

        if ($trainer->hasCertificationExpired()) {
            throw BookingException::trainerCertificationExpired();
        }

        if ($session->isLockedForTrainer($trainer)) {
            throw BookingException::sessionExclusive();
        }

        $days = $trainer->advanceBookingDays();

        if ($session->starts_at->gt(now()->addDays($days)->endOfDay())) {
            throw BookingException::beyondAdvanceWindow($days);
        }
    }

    protected function assertMemberCanBook(WorkoutSession $session, Member $member, Trainer $trainer): void
    {
        if ($member->branch_id !== $session->branch_id) {
            throw BookingException::branchMismatch();
        }

        if ($member->isSuspended()) {
            throw BookingException::memberSuspended($member->suspended_until?->format('d/m/Y'));
        }

        if ($member->status !== MemberStatus::Active) {
            throw BookingException::memberInactive();
        }

        $inTeam = $trainer->teamMemberships()
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->exists();

        if (! $inTeam) {
            throw BookingException::memberNotInTeam();
        }

        $duplicate = Booking::where('workout_session_id', $session->id)
            ->where('active_member_key', $member->id)
            ->exists();

        if ($duplicate) {
            throw BookingException::alreadyBooked();
        }

        $this->assertNoTimeConflict($session, $member);
    }

    /** กันลูกทีมถูกจองซ้อนเวลาเดียวกันโดยเทรนเนอร์คนละคน */
    protected function assertNoTimeConflict(WorkoutSession $session, Member $member): void
    {
        $conflict = Booking::query()
            ->where('member_id', $member->id)
            ->whereIn('status', [BookingStatus::Booked->value, BookingStatus::CheckedIn->value])
            ->whereHas('workoutSession', function ($q) use ($session) {
                $q->where('id', '!=', $session->id)
                    ->where('starts_at', '<', $session->ends_at)
                    ->where('ends_at', '>', $session->starts_at);
            })
            ->with('workoutSession')
            ->first();

        if ($conflict) {
            throw BookingException::timeConflict($conflict->workoutSession->timeLabel());
        }
    }

    // --- เครดิต ---

    /** ตัดเครดิตจากแพ็กเกจที่ใกล้หมดอายุที่สุดก่อน */
    protected function consumeCredit(Member $member): MemberPackage
    {
        $package = $member->packages()
            ->active()
            ->whereColumn('credits_used', '<', 'credits_total')
            ->orderBy('expires_at')
            ->lockForUpdate()
            ->first();

        if (! $package) {
            throw BookingException::noCredits();
        }

        $package->increment('credits_used');

        if ($package->fresh()->creditsRemaining() === 0) {
            $package->update(['status' => MemberPackage::STATUS_EXHAUSTED]);
        }

        return $package;
    }

    protected function refundCredit(Booking $booking): void
    {
        $package = $booking->memberPackage;

        if (! $package) {
            return;
        }

        $package->decrement('credits_used');

        if ($package->status === MemberPackage::STATUS_EXHAUSTED && ! $package->fresh()->isExpired()) {
            $package->update(['status' => MemberPackage::STATUS_ACTIVE]);
        }

        $booking->credit_consumed = false;
    }

    // --- ตัวช่วย ---

    protected function createBooking(
        WorkoutSession $session,
        Member $member,
        Trainer $trainer,
        ?User $actor,
        bool $isWaitlist,
        ?MemberPackage $package,
    ): Booking {
        try {
            return Booking::create([
                'workout_session_id' => $session->id,
                'member_id' => $member->id,
                'trainer_id' => $trainer->id,
                'booked_by_user_id' => $actor?->id,
                'status' => $isWaitlist ? BookingStatus::Waitlisted : BookingStatus::Booked,
                // คิวสำรองไม่กินคีย์ unique จึงเว้น active_member_key ไว้เป็น NULL
                'active_member_key' => $isWaitlist ? null : $member->id,
                'waitlist_position' => $isWaitlist ? $this->nextWaitlistPosition($session) : null,
                'member_package_id' => $package?->id,
                'credit_consumed' => $package !== null,
            ]);
        } catch (QueryException $e) {
            // ด่านสุดท้ายจาก unique index เผื่อมีคำขอลอดผ่านการตรวจข้างบนมาพร้อมกัน
            if ($this->isDuplicateKey($e)) {
                throw BookingException::alreadyBooked();
            }

            throw $e;
        }
    }

    protected function nextWaitlistPosition(WorkoutSession $session): int
    {
        return (int) $session->bookings()
            ->where('status', BookingStatus::Waitlisted->value)
            ->max('waitlist_position') + 1;
    }

    protected function resequenceWaitlist(WorkoutSession $session): void
    {
        $session->bookings()
            ->where('status', BookingStatus::Waitlisted->value)
            ->orderByRaw('waitlist_position IS NULL, waitlist_position ASC')
            ->orderBy('id')
            ->get()
            ->each(fn (Booking $b, int $i) => $b->update(['waitlist_position' => $i + 1]));
    }

    /** รอบแบบเหมา: เทรนเนอร์คนแรกที่จองได้สิทธิ์ทั้งรอบ */
    protected function claimIfExclusive(WorkoutSession $session, Trainer $trainer): void
    {
        if ($session->mode === \App\Enums\SessionMode::Exclusive && $session->claimed_by_trainer_id === null) {
            $session->update(['claimed_by_trainer_id' => $trainer->id]);
        }
    }

    /** คืนรอบเหมาให้ว่างเมื่อเทรนเนอร์ยกเลิกที่นั่งสุดท้ายของตัวเอง */
    protected function releaseExclusiveClaim(WorkoutSession $session, Booking $booking): void
    {
        if ($session->claimed_by_trainer_id !== $booking->trainer_id) {
            return;
        }

        $remaining = $session->activeBookings()->where('trainer_id', $booking->trainer_id)->count();

        if ($remaining === 0) {
            $session->update(['claimed_by_trainer_id' => null]);
        }
    }

    protected function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
