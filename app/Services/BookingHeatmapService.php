<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * รวมข้อมูลความหนาแน่นการจองเป็นตาราง วันในสัปดาห์ x ชั่วโมง
 *
 * ใช้ร่วมกันทั้งหน้าแอดมินและหน้าสถิติของเทรนเนอร์
 * จะได้ไม่เขียนคิวรีซ้ำสองที่แล้วนิยามตัวเลขหลุดกันทีหลัง
 */
class BookingHeatmapService
{
    /** จันทร์เป็นวันแรก ให้ตรงกับแถบเลือกวันในหน้าจอง (คีย์คือค่าจาก DAYOFWEEK ของ MySQL) */
    public const DAYS = [2 => 'จันทร์', 3 => 'อังคาร', 4 => 'พุธ', 5 => 'พฤหัส', 6 => 'ศุกร์', 7 => 'เสาร์', 1 => 'อาทิตย์'];

    /**
     * @param  int|null  $trainerId  ระบุเมื่อต้องการดูเฉพาะการจองของเทรนเนอร์คนนั้น
     * @return array{hours: array<int,int>, cells: array<int, array<int, ?array>>}
     */
    public function grid(int $branchId, CarbonInterface $from, CarbonInterface $to, ?int $trainerId = null): array
    {
        $sessions = DB::table('workout_sessions')
            ->where('branch_id', $branchId)
            ->whereBetween('starts_at', [$from, $to])
            ->where('status', '!=', SessionStatus::Cancelled->value)
            ->selectRaw('DAYOFWEEK(starts_at) as dow, HOUR(starts_at) as hr,
                         COUNT(*) as sessions, SUM(capacity) as capacity')
            ->groupBy('dow', 'hr')
            ->get()
            ->keyBy(fn ($r) => $r->hr.'-'.$r->dow);

        // นับเฉพาะการจองที่เคยกินที่นั่งจริง คนที่ไม่มาตามนัดก็ยังนับเป็นความต้องการ
        // ถ้าไม่นับจะประเมินความต้องการต่ำกว่าความจริง
        $occupied = [
            BookingStatus::Booked->value,
            BookingStatus::CheckedIn->value,
            BookingStatus::Completed->value,
            BookingStatus::NoShow->value,
        ];

        // ความต้องการที่ล้นนับจากตำแหน่งคิวที่ยังค้าง
        // เพราะคนที่ถูกเลื่อนขึ้นได้ที่นั่งแล้วจะถูกล้างตำแหน่งคิวทิ้ง
        $bookings = DB::table('bookings')
            ->join('workout_sessions', 'workout_sessions.id', '=', 'bookings.workout_session_id')
            ->where('workout_sessions.branch_id', $branchId)
            ->whereBetween('workout_sessions.starts_at', [$from, $to])
            ->when($trainerId, fn ($q) => $q->where('bookings.trainer_id', $trainerId))
            ->selectRaw('DAYOFWEEK(workout_sessions.starts_at) as dow, HOUR(workout_sessions.starts_at) as hr,
                         SUM(CASE WHEN bookings.status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as occupied,
                         SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as no_shows,
                         SUM(CASE WHEN bookings.waitlist_position IS NOT NULL THEN 1 ELSE 0 END) as waitlisted',
                array_merge($occupied, [BookingStatus::NoShow->value]))
            ->groupBy('dow', 'hr')
            ->get()
            ->keyBy(fn ($r) => $r->hr.'-'.$r->dow);

        $hours = $sessions->pluck('hr')->unique()->sort()->values()->all();
        $cells = [];

        foreach ($hours as $hr) {
            foreach (array_keys(self::DAYS) as $dow) {
                $key = $hr.'-'.$dow;
                $s = $sessions->get($key);

                if (! $s) {
                    $cells[$hr][$dow] = null;

                    continue;
                }

                $b = $bookings->get($key);
                $capacity = (int) $s->capacity;
                $used = (int) ($b->occupied ?? 0);

                $cells[$hr][$dow] = [
                    'hr' => $hr,
                    'dow' => $dow,
                    'sessions' => (int) $s->sessions,
                    'capacity' => $capacity,
                    'occupied' => $used,
                    'rate' => $capacity > 0 ? $used / $capacity : 0,
                    'noShows' => (int) ($b->no_shows ?? 0),
                    'waitlisted' => (int) ($b->waitlisted ?? 0),
                ];
            }
        }

        return ['hours' => $hours, 'cells' => $cells];
    }

    /** ข้อสรุปที่นำไปตัดสินใจได้ ไม่ใช่แค่ตัวเลขดิบ */
    public function insights(array $grid): array
    {
        $flat = collect($grid['cells'])
            ->flatMap(fn ($row) => array_values($row))
            ->filter(fn ($c) => $c && $c['sessions'] > 0)
            ->values();

        if ($flat->isEmpty()) {
            return ['busiest' => [], 'quietest' => [], 'unmet' => [], 'totals' => null];
        }

        $capacity = $flat->sum('capacity');
        $used = $flat->sum('occupied');
        $noShows = $flat->sum('noShows');

        return [
            'busiest' => $flat->sortByDesc('rate')->take(3)->values()->all(),

            // ว่างเรื้อรังคือช่องที่เปิดมาแล้วหลายครั้งแต่ใช้ที่นั่งไม่ถึงหนึ่งในสาม
            'quietest' => $flat->where('sessions', '>=', 3)->where('rate', '<', 0.34)
                ->sortBy('rate')->take(3)->values()->all(),

            'unmet' => $flat->where('waitlisted', '>', 0)->sortByDesc('waitlisted')->take(3)->values()->all(),

            'totals' => [
                'capacity' => $capacity,
                'occupied' => $used,
                'rate' => $capacity > 0 ? $used / $capacity : 0,
                'noShows' => $noShows,
                'noShowRate' => $used > 0 ? $noShows / $used : 0,
            ],
        ];
    }

    /** ระดับความหนาแน่น 0-6 ใช้เลือกสีของช่อง */
    public function level(float $rate): int
    {
        return match (true) {
            $rate >= 0.90 => 6,
            $rate >= 0.75 => 5,
            $rate >= 0.60 => 4,
            $rate >= 0.45 => 3,
            $rate >= 0.25 => 2,
            $rate > 0 => 1,
            default => 0,
        };
    }
}
