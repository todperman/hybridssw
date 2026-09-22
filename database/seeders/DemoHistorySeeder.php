<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Trainer;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * สร้างประวัติการจองย้อนหลังไว้ให้หน้า heatmap มีอะไรให้ดู
 *
 * รันแยกจาก DatabaseSeeder ด้วย: php artisan db:seed --class=DemoHistorySeeder
 * ไม่ผ่าน BookingService เพราะ service ตั้งใจห้ามจองรอบที่ผ่านไปแล้ว
 * จึงต้องดูแลให้ booked_count ตรงกับจำนวนแถวเอง
 */
class DemoHistorySeeder extends Seeder
{
    /** น้ำหนักความต้องการตามชั่วโมง เย็นแน่นสุด เช้ารองลงมา บ่ายเงียบ */
    protected const DEMAND = [
        6 => 0.55, 7 => 0.70, 8 => 0.45, 9 => 0.30,
        12 => 0.35, 13 => 0.25, 14 => 0.20, 15 => 0.25, 16 => 0.45,
        17 => 0.85, 18 => 0.98, 19 => 0.90, 20 => 0.60,
    ];

    public function run(): void
    {
        $weeks = 10;

        foreach (Branch::active()->get() as $branch) {
            $trainers = Trainer::approved()->where('branch_id', $branch->id)->with('teamMembers')->get();

            if ($trainers->isEmpty()) {
                continue;
            }

            $created = $this->buildFor($branch, $trainers, $weeks);

            $this->command?->info("{$branch->code}: สร้างประวัติ {$created} รอบย้อนหลัง {$weeks} สัปดาห์");
        }
    }

    protected function buildFor(Branch $branch, $trainers, int $weeks): int
    {
        $templates = $branch->scheduleTemplates()->active()->get();
        $start = CarbonImmutable::now()->subWeeks($weeks)->startOfDay();
        $end = CarbonImmutable::now()->subDay()->endOfDay();
        $count = 0;

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            foreach ($templates as $template) {
                if ($template->day_of_week !== $date->dayOfWeek) {
                    continue;
                }

                [$h, $m] = array_pad(explode(':', $template->start_time), 2, '0');
                [$eh, $em] = array_pad(explode(':', $template->end_time), 2, '0');

                $cursor = $date->setTime((int) $h, (int) $m);
                $limit = $date->setTime((int) $eh, (int) $em);

                while ($cursor->addHour()->lte($limit)) {
                    $count += $this->makeSession($branch, $trainers, $cursor, $template->capacity) ? 1 : 0;
                    $cursor = $cursor->addHour();
                }
            }
        }

        return $count;
    }

    protected function makeSession(Branch $branch, $trainers, CarbonImmutable $startsAt, int $capacity): bool
    {
        $session = WorkoutSession::firstOrCreate(
            ['branch_id' => $branch->id, 'starts_at' => $startsAt],
            [
                'date' => $startsAt->toDateString(),
                'ends_at' => $startsAt->addHour(),
                'capacity' => $capacity,
                'status' => SessionStatus::Completed,
            ],
        );

        if (! $session->wasRecentlyCreated) {
            return false;
        }

        $demand = self::DEMAND[(int) $startsAt->format('G')] ?? 0.3;

        // สุดสัปดาห์คนน้อยลงเล็กน้อย และเติมความสุ่มให้ไม่เท่ากันทุกสัปดาห์
        $weekend = in_array($startsAt->dayOfWeek, [0, 6], true) ? 0.8 : 1.0;
        $wanted = (int) round($capacity * $demand * $weekend * (0.75 + mt_rand(0, 50) / 100));

        $pool = $trainers->flatMap(fn (Trainer $t) => $t->teamMembers->map(fn (Member $m) => [$t, $m]))->shuffle();

        $seated = 0;
        $waitlisted = 0;

        foreach ($pool as [$trainer, $member]) {
            if ($seated >= $wanted) {
                break;
            }

            if ($seated >= $capacity) {
                // ความต้องการล้นที่นั่ง กลายเป็นคิวสำรอง ซึ่งเป็นสัญญาณให้เปิดรอบเพิ่ม
                if ($waitlisted < 2 && $demand >= 0.85) {
                    $this->makeBooking($session, $member, $trainer, BookingStatus::Cancelled, ++$waitlisted);
                }

                break;
            }

            // คนไม่มาตามนัดราวหนึ่งในสิบ
            $status = mt_rand(1, 10) === 1 ? BookingStatus::NoShow : BookingStatus::Completed;

            $this->makeBooking($session, $member, $trainer, $status);
            $seated++;
        }

        // ผู้ที่ไม่มาตามนัดไม่นับเป็นที่นั่งที่ใช้อยู่ ให้ตรงกับกติกาใน BookingService
        $active = $session->activeBookings()->count();
        $session->update(['booked_count' => $active, 'waitlist_count' => 0]);

        return true;
    }

    protected function makeBooking(WorkoutSession $session, Member $member, Trainer $trainer, BookingStatus $status, ?int $position = null): void
    {
        DB::table('bookings')->insert([
            'reference' => 'BK'.$session->starts_at->format('ymd').strtoupper(\Illuminate\Support\Str::random(5)),
            'workout_session_id' => $session->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'status' => $status->value,
            // คิวสำรองที่จบไปโดยไม่ได้เลื่อนขึ้น ถูกบันทึกเป็นยกเลิกพร้อมตำแหน่งคิวเดิม
            'waitlist_position' => $position,
            'active_member_key' => $status->occupiesSeat() ? $member->id : null,
            'credit_consumed' => $status->occupiesSeat(),
            'created_at' => $session->starts_at->subDays(2),
            'updated_at' => $session->starts_at,
        ]);
    }
}
