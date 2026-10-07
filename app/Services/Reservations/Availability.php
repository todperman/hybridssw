<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Enums\TrainerStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\SlotLock;
use App\Models\Trainer;
use App\Models\TrainerAvailability;
use App\Models\TrainerTimeOff;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * ตอบว่าช่วงเวลาไหนว่างสำหรับยิม Trainer และสมาชิก
 *
 * ใช้แสดงผลและตรวจล่วงหน้าเพื่อบอกเหตุผลที่อ่านเข้าใจได้
 * ตัวกันจองซ้อนจริงคือ unique index ของ slot_locks ตอนบันทึก ไม่ใช่คลาสนี้
 */
class Availability
{
    public function __construct(protected OpeningHours $hours) {}

    /**
     * ชั่วโมงที่ทรัพยากรนี้ถูกล็อกอยู่จริง
     * ไม่นับรายการรอชำระที่หมดเวลาแล้ว แม้ job ยังไม่ทันลบล็อกก็ถือว่าว่าง
     *
     * @return array<string, true> คีย์เป็น 'Y-m-d H:i'
     */
    public function lockedSlots(string $resource, int $id, CarbonInterface $from, CarbonInterface $to, ?int $ignoreReservationId = null): array
    {
        return SlotLock::query()
            ->where('resource', $resource)
            ->where('resource_id', $id)
            ->where('slot_start', '>=', $from)
            ->where('slot_start', '<', $to)
            ->when($ignoreReservationId, fn ($q) => $q->where('reservation_id', '!=', $ignoreReservationId))
            ->whereHas('reservation', fn ($q) => $q->where(fn ($q) => $q
                ->where('status', ReservationStatus::Confirmed->value)
                ->orWhere(fn ($q) => $q
                    ->where('status', ReservationStatus::PendingPayment->value)
                    ->where('hold_expires_at', '>', now()))))
            ->pluck('slot_start')
            ->mapWithKeys(fn ($s) => [CarbonImmutable::parse($s)->format('Y-m-d H:i') => true])
            ->all();
    }

    /**
     * เวลาเริ่มที่จองยิมได้ในวันนั้น สำหรับความยาว $hours ชั่วโมงติดกัน
     *
     * @return array<int, CarbonImmutable>
     */
    public function gymStartTimes(Branch $branch, CarbonInterface $date, int $hours, ?int $ignoreReservationId = null): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        // รวมวันถัดไปด้วย การจองตอนดึกอาจต่อข้ามเที่ยงคืนได้ถ้ายิมเปิดต่อเนื่อง
        $open = collect($this->hours->slotsOn($branch, $date))
            ->merge($this->hours->slotsOn($branch, $date->addDay()))
            ->mapWithKeys(fn (CarbonImmutable $s) => [$s->format('Y-m-d H:i') => true])
            ->all();

        $locked = $this->lockedSlots(SlotLock::GYM, $branch->id, $date, $date->addDays(2), $ignoreReservationId);

        $starts = [];

        foreach ($this->hours->slotsOn($branch, $date) as $start) {
            if ($start->lte(now())) {
                continue;
            }

            if ($this->rangeFits($start, $hours, $open, $locked)) {
                $starts[] = $start;
            }
        }

        return $starts;
    }

    /** ยิมเปิดและว่างครบทุกชั่วโมงในช่วงนี้ */
    public function gymFree(Branch $branch, CarbonInterface $start, int $hours, ?int $ignoreReservationId = null): bool
    {
        $start = CarbonImmutable::parse($start);

        foreach (range(0, $hours - 1) as $i) {
            if (! $this->hours->isOpenSlot($branch, $start->addHours($i))) {
                return false;
            }
        }

        return $this->lockedSlots(SlotLock::GYM, $branch->id, $start, $start->addHours($hours), $ignoreReservationId) === [];
    }

    public function gymOpen(Branch $branch, CarbonInterface $start, int $hours): bool
    {
        $start = CarbonImmutable::parse($start);

        foreach (range(0, $hours - 1) as $i) {
            if (! $this->hours->isOpenSlot($branch, $start->addHours($i))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Trainer รับงานช่วงนี้ได้ครบทุกชั่วโมงหรือไม่
     * ต้องอยู่ในเวลาว่างที่ตั้งไว้ ไม่ตรงวันลา และไม่ติดงานอื่น
     */
    public function trainerCovers(Trainer $trainer, CarbonInterface $start, int $hours, ?int $ignoreReservationId = null, bool $checkLocks = true): bool
    {
        if (! $trainer->canTakeReservations()) {
            return false;
        }

        $start = CarbonImmutable::parse($start);

        if (! $this->coveredBySchedule($trainer, $start, $hours)) {
            return false;
        }

        if (! $checkLocks) {
            return true;
        }

        return $this->lockedSlots(SlotLock::TRAINER, $trainer->id, $start, $start->addHours($hours), $ignoreReservationId) === [];
    }

    /**
     * เวลาว่างที่ Trainer ตั้งไว้ครอบช่วงนี้ครบ และไม่ตรงวันลา
     * ไม่ดูสถานะเปิดรับงาน ใช้ตรวจว่าการแก้เวลาว่างจะไปตัดงานที่มีอยู่แล้วหรือไม่
     */
    public function coveredBySchedule(Trainer $trainer, CarbonInterface $start, int $hours): bool
    {
        $start = CarbonImmutable::parse($start);
        $end = $start->addHours($hours);

        $windows = $trainer->availabilities()->get();
        $offs = $trainer->timeOffs()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        foreach (range(0, $hours - 1) as $i) {
            $slot = $start->addHours($i);

            if (! $this->insideWindows($slot, $windows) || $this->insideTimeOff($slot, $offs)) {
                return false;
            }
        }

        return true;
    }

    /** Trainer ทุกคนในสาขาที่ว่างครบทุกชั่วโมงของช่วงนี้ */
    public function availableTrainers(Branch $branch, CarbonInterface $start, int $hours, ?int $ignoreReservationId = null): Collection
    {
        return Trainer::query()
            ->where('branch_id', $branch->id)
            ->where('status', TrainerStatus::Approved->value)
            ->where('accepts_bookings', true)
            ->with('user')
            ->get()
            ->filter(fn (Trainer $t) => $this->trainerCovers($t, $start, $hours, $ignoreReservationId))
            ->values();
    }

    public function memberFree(Member $member, CarbonInterface $start, int $hours, ?int $ignoreReservationId = null): bool
    {
        $start = CarbonImmutable::parse($start);

        return $this->lockedSlots(SlotLock::MEMBER, $member->id, $start, $start->addHours($hours), $ignoreReservationId) === [];
    }

    // --- ตัวช่วย ---

    protected function rangeFits(CarbonImmutable $start, int $hours, array $open, array $locked): bool
    {
        foreach (range(0, $hours - 1) as $i) {
            $key = $start->addHours($i)->format('Y-m-d H:i');

            if (! isset($open[$key]) || isset($locked[$key])) {
                return false;
            }
        }

        return true;
    }

    /** @param  Collection<int, TrainerAvailability>  $windows */
    protected function insideWindows(CarbonImmutable $slot, Collection $windows): bool
    {
        $from = $slot->hour * 60 + $slot->minute;
        $to = $from + OpeningHours::SLOT_MINUTES;

        foreach ($windows as $w) {
            $sameDay = $w->date
                ? $w->date->isSameDay($slot)
                : $w->day_of_week === $slot->dayOfWeek;

            if ($sameDay && $this->minutes($w->start_time) <= $from && $to <= $this->minutes($w->end_time)) {
                return true;
            }
        }

        return false;
    }

    /** @param  Collection<int, TrainerTimeOff>  $offs */
    protected function insideTimeOff(CarbonImmutable $slot, Collection $offs): bool
    {
        $from = $slot->hour * 60 + $slot->minute;
        $to = $from + OpeningHours::SLOT_MINUTES;

        foreach ($offs as $off) {
            if (! $off->date->isSameDay($slot)) {
                continue;
            }

            if ($off->isWholeDay()) {
                return true;
            }

            // ทับกันแม้แค่บางส่วนก็ถือว่าไม่ว่าง
            if ($from < $this->minutes($off->end_time) && $this->minutes($off->start_time) < $to) {
                return true;
            }
        }

        return false;
    }

    protected function minutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return (int) $h * 60 + (int) $m;
    }
}
