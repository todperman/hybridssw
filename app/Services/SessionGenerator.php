<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Models\Branch;
use App\Models\ScheduleException;
use App\Models\ScheduleTemplate;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * แปลงกฎเวลาเปิด (ScheduleTemplate) + ข้อยกเว้นรายวัน (ScheduleException)
 * ให้กลายเป็นรอบจริงในตาราง workout_sessions
 *
 * เหตุผลที่ไม่เก็บทุกชั่วโมงล่วงหน้าเป็นปี: แอดมินแก้เวลาเปิด-ปิดได้ตลอด
 * ถ้า materialise ไว้หมดจะต้องไล่แก้ย้อนหลังทุกครั้ง จึงสร้างล่วงหน้าเท่าที่เปิดให้จอง
 */
class SessionGenerator
{
    /**
     * สร้างรอบให้สาขาหนึ่งในช่วงวันที่ที่กำหนด
     *
     * @return array{created:int, skipped:int}
     */
    public function generateForBranch(Branch $branch, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $from = CarbonImmutable::parse($from ?? now())->startOfDay();
        $to = CarbonImmutable::parse($to ?? now()->addDays($branch->session_horizon_days))->startOfDay();

        $templates = $branch->scheduleTemplates()->active()->get();

        $exceptions = $branch->scheduleExceptions()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn (ScheduleException $e) => $e->date->toDateString());

        $created = 0;
        $skipped = 0;

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $result = $this->generateForDate(
                $branch,
                $date,
                $templates,
                $exceptions->get($date->toDateString()) ?? collect(),
            );

            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * สร้างรอบของวันเดียว
     *
     * @param  Collection<int, ScheduleTemplate>  $templates
     * @param  Collection<int, ScheduleException>  $exceptions
     * @return array{created:int, skipped:int}
     */
    public function generateForDate(
        Branch $branch,
        CarbonInterface $date,
        ?Collection $templates = null,
        ?Collection $exceptions = null,
    ): array {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $templates ??= $branch->scheduleTemplates()->active()->get();
        $exceptions ??= $branch->scheduleExceptions()->whereDate('date', $date->toDateString())->get();

        // ปิดทั้งวันชนะทุกกฎ
        if ($exceptions->firstWhere('type', ScheduleException::TYPE_CLOSED)) {
            return ['created' => 0, 'skipped' => 0];
        }

        $ranges = $this->resolveRanges($branch, $date, $templates, $exceptions);

        $created = 0;
        $skipped = 0;

        foreach ($ranges as $range) {
            foreach ($this->slotsIn($date, $range) as $slot) {
                // รอบที่ผ่านไปแล้วไม่ต้องสร้าง
                if ($slot['starts_at']->isPast()) {
                    continue;
                }

                // unique(branch_id, starts_at) กันซ้ำอยู่แล้ว firstOrCreate จึงปลอดภัย
                $session = WorkoutSession::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'starts_at' => $slot['starts_at'],
                    ],
                    [
                        'schedule_template_id' => $range['template_id'],
                        'date' => $date->toDateString(),
                        'ends_at' => $slot['ends_at'],
                        'capacity' => $range['capacity'],
                        'status' => SessionStatus::Open,
                    ],
                );

                $session->wasRecentlyCreated ? $created++ : $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * รวมช่วงเวลาเปิดของวันนั้น
     * custom_hours จะแทนที่กฎประจำทั้งหมด ส่วน special_open เป็นการเปิดเพิ่ม
     *
     * @return array<int, array{start:string, end:string, duration:int, capacity:int, template_id:?int}>
     */
    protected function resolveRanges(
        Branch $branch,
        CarbonInterface $date,
        Collection $templates,
        Collection $exceptions,
    ): array {
        $ranges = [];

        $custom = $exceptions->firstWhere('type', ScheduleException::TYPE_CUSTOM_HOURS);

        if ($custom && $custom->start_time && $custom->end_time) {
            $ranges[] = [
                'start' => $custom->start_time,
                'end' => $custom->end_time,
                'duration' => $branch->slot_duration_minutes,
                'capacity' => $custom->capacity ?? $branch->default_capacity,
                'template_id' => null,
            ];
        } else {
            foreach ($templates as $template) {
                if (! $template->appliesOn($date)) {
                    continue;
                }

                $ranges[] = [
                    'start' => $template->start_time,
                    'end' => $template->end_time,
                    'duration' => $template->slot_duration_minutes,
                    'capacity' => $template->capacity,
                    'template_id' => $template->id,
                ];
            }
        }

        $special = $exceptions->firstWhere('type', ScheduleException::TYPE_SPECIAL_OPEN);

        if ($special && $special->start_time && $special->end_time) {
            $ranges[] = [
                'start' => $special->start_time,
                'end' => $special->end_time,
                'duration' => $branch->slot_duration_minutes,
                'capacity' => $special->capacity ?? $branch->default_capacity,
                'template_id' => null,
            ];
        }

        return $ranges;
    }

    /**
     * ซอยช่วงเวลาเป็นรอบย่อยตาม duration
     * รอบสุดท้ายที่ไม่เต็มความยาวจะถูกตัดทิ้ง
     *
     * @return array<int, array{starts_at:CarbonImmutable, ends_at:CarbonImmutable}>
     */
    protected function slotsIn(CarbonInterface $date, array $range): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        $cursor = $this->applyTime($date, $range['start']);
        $limit = $this->applyTime($date, $range['end']);

        // ช่วงข้ามเที่ยงคืน เช่น 22:00 - 02:00
        if ($limit->lte($cursor)) {
            $limit = $limit->addDay();
        }

        $slots = [];

        while ($cursor->addMinutes($range['duration'])->lte($limit)) {
            $ends = $cursor->addMinutes($range['duration']);

            $slots[] = ['starts_at' => $cursor, 'ends_at' => $ends];

            $cursor = $ends;
        }

        return $slots;
    }

    protected function applyTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return $date->setTime((int) $h, (int) $m);
    }

    /**
     * ปิดรอบทั้งวันเมื่อแอดมินประกาศวันหยุดย้อนหลัง
     * รอบที่มีคนจองไว้แล้วจะถูกทำเป็น cancelled เพื่อให้เห็นว่าต้องแจ้งลูกค้า
     */
    public function closeDay(Branch $branch, CarbonInterface $date, ?string $reason = null): int
    {
        return DB::transaction(function () use ($branch, $date, $reason) {
            return WorkoutSession::where('branch_id', $branch->id)
                ->whereDate('date', CarbonImmutable::parse($date)->toDateString())
                ->whereIn('status', [SessionStatus::Open->value, SessionStatus::Closed->value])
                ->update([
                    'status' => SessionStatus::Cancelled->value,
                    'close_reason' => $reason ?? 'ปิดทำการ',
                    'updated_at' => now(),
                ]);
        });
    }
}
