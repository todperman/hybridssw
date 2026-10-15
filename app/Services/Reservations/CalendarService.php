<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Enums\TrainerStatus;
use App\Models\Branch;
use App\Models\Reservation;
use App\Models\SlotLock;
use App\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * ข้อมูลสำหรับหน้าปฏิทินความว่าง
 *
 * ทั้งเดือนถามล็อกของยิมครั้งเดียว ไม่ใช่วันละครั้ง
 * รายชั่วโมงของวันเดียวนับ Trainer ที่ว่างต่อชั่วโมงจากข้อมูลที่โหลดครั้งเดียวเช่นกัน
 * ไม่บอกว่าใครจองช่วงไหน เห็นแค่ "จองแล้ว" ยกเว้นการจองของผู้ดูเอง
 */
class CalendarService
{
    public function __construct(protected OpeningHours $hours, protected Availability $availability) {}

    /**
     * สรุปรายวันของทั้งเดือน
     *
     * @return array<string, array{open: int, free: int, longest: int, closed: bool, past: bool, beyond: bool}>
     */
    public function month(Branch $branch, CarbonInterface $month): array
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();
        $end = $month->endOfMonth();
        $today = CarbonImmutable::today();
        $limit = $today->addDays((int) $branch->booking_window_days)->endOfDay();

        $locked = $this->availability->lockedSlots(SlotLock::GYM, $branch->id, $month, $end->addDay());
        $days = [];

        for ($day = $month; $day->lte($end); $day = $day->addDay()) {
            $slots = $this->hours->slotsOn($branch, $day);
            $free = 0;
            $run = 0;
            $longest = 0;

            foreach ($slots as $slot) {
                $isFree = ! isset($locked[$slot->format('Y-m-d H:i')]) && $slot->gt(now());

                if ($isFree) {
                    $free++;
                    $run++;
                    $longest = max($longest, $run);
                } else {
                    $run = 0;
                }
            }

            $days[$day->toDateString()] = [
                'open' => count($slots),
                'free' => $free,
                'longest' => $longest,
                'closed' => $slots === [],
                'past' => $day->lt($today),
                'beyond' => $day->gt($limit),
            ];
        }

        return $days;
    }

    /**
     * รายชั่วโมงของวันเดียว
     *
     * @return array<int, array{start: CarbonImmutable, status: string, trainers: int, reference: ?string, run: int}>
     *               status: free | taken | mine | past
     *               run: ชั่วโมงว่างต่อเนื่องนับจากช่องนี้ (ใช้ตัดสินว่าจองหลายชั่วโมงจากช่องนี้ได้ไหม)
     */
    public function day(Branch $branch, CarbonInterface $date, ?User $viewer = null): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $slots = $this->hours->slotsOn($branch, $date);

        if ($slots === []) {
            return [];
        }

        $from = $slots[0];
        $to = end($slots)->addHour();

        $locked = $this->availability->lockedSlots(SlotLock::GYM, $branch->id, $from, $to);
        $mine = $this->mine($viewer, $from, $to);
        $trainerFree = $this->trainerCounts($branch, $slots, $from, $to);

        $rows = [];

        foreach ($slots as $slot) {
            $key = $slot->format('Y-m-d H:i');

            $rows[] = [
                'start' => $slot,
                'status' => match (true) {
                    isset($mine[$key]) => 'mine',
                    isset($locked[$key]) => 'taken',
                    $slot->lte(now()) => 'past',
                    default => 'free',
                },
                'trainers' => $trainerFree[$key] ?? 0,
                'reference' => $mine[$key] ?? null,
                'run' => 0,
            ];
        }

        // นับชั่วโมงว่างต่อเนื่องจากท้ายมาหน้า ช่องว่างแต่ละช่องจะรู้ว่าจองยาวได้กี่ชั่วโมง
        $run = 0;
        $prev = null;

        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $contiguous = $prev !== null && $rows[$i]['start']->addHour()->equalTo($prev);
            $run = $rows[$i]['status'] === 'free' ? ($contiguous ? $run + 1 : 1) : 0;
            $rows[$i]['run'] = $run;
            $prev = $rows[$i]['start'];
        }

        return $rows;
    }

    /** @return array<string, string> slot => reference ของการจองที่ผู้ดูเกี่ยวข้อง */
    protected function mine(?User $viewer, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $viewer || (! $viewer->member && ! $viewer->trainer)) {
            return [];
        }

        $reservations = Reservation::query()
            ->whereIn('status', ReservationStatus::holding())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->where(function ($q) use ($viewer) {
                if ($viewer->member) {
                    $q->orWhere('payer_member_id', $viewer->member->id)
                        ->orWhereHas('participants', fn ($p) => $p->whereKey($viewer->member->id));
                }

                if ($viewer->trainer) {
                    $q->orWhere('trainer_id', $viewer->trainer->id);
                }
            })
            ->get(['id', 'reference', 'starts_at', 'hours']);

        $mine = [];

        foreach ($reservations as $r) {
            foreach ($r->slots() as $slot) {
                $mine[$slot->format('Y-m-d H:i')] = $r->reference;
            }
        }

        return $mine;
    }

    /** @return array<string, int> slot => จำนวน Trainer ที่ว่างชั่วโมงนั้น */
    protected function trainerCounts(Branch $branch, array $slots, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $trainers = Trainer::query()
            ->where('branch_id', $branch->id)
            ->where('status', TrainerStatus::Approved->value)
            ->where('accepts_bookings', true)
            ->with(['availabilities', 'timeOffs' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()])])
            ->get()
            ->filter(fn (Trainer $t) => $t->canTakeReservations());

        if ($trainers->isEmpty()) {
            return [];
        }

        $locked = $this->availability->lockedSlotsFor(SlotLock::TRAINER, $trainers->modelKeys(), $from, $to);
        $counts = [];

        foreach ($slots as $slot) {
            $key = $slot->format('Y-m-d H:i');
            $counts[$key] = $trainers
                ->filter(fn (Trainer $t) => ! isset($locked[$t->id][$key]) && $this->availability->coveredBySchedule($t, $slot, 1))
                ->count();
        }

        return $counts;
    }

    /** @return Collection<int, CarbonImmutable> */
    public static function gridDays(CarbonInterface $month): Collection
    {
        $start = CarbonImmutable::parse($month)->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = CarbonImmutable::parse($month)->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $days = collect();

        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $days->push($d);
        }

        return $days;
    }
}
