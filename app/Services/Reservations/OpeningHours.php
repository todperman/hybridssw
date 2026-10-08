<?php

namespace App\Services\Reservations;

use App\Models\Branch;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * ยิมเปิดช่วงไหนบ้าง ตัดเป็นช่วงละ 1 ชั่วโมง
 *
 * อ่านจาก "ตารางเวลาเปิด" (ประจำสัปดาห์) และ "วันหยุด / เวลาพิเศษ" (รายวัน)
 * ปิดทั้งวันชนะทุกกฎ เปลี่ยนเวลาเปิด-ปิดแทนที่ตารางประจำ ส่วนเปิดพิเศษเพิ่มเข้าไป
 * ไม่เก็บช่วงเวลาไว้ล่วงหน้าในฐานข้อมูล คำนวณสดทุกครั้ง แอดมินแก้เวลาเปิดได้ทันทีโดยไม่ต้องสร้างใหม่
 */
class OpeningHours
{
    public const SLOT_MINUTES = 60;

    /** @var array<string, array<int, CarbonImmutable>> */
    protected array $cache = [];

    /** @var array<int, \Illuminate\Support\Collection> ตารางเวลาเปิดของแต่ละสาขา ใช้ซ้ำทุกวันที่ถามในคำขอเดียวกัน */
    protected array $templates = [];

    /**
     * จุดเริ่มของทุกช่วง 1 ชั่วโมงที่ยิมเปิดในวันนั้น เรียงตามเวลา
     *
     * @return array<int, CarbonImmutable>
     */
    public function slotsOn(Branch $branch, CarbonInterface $date): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $key = $branch->id.'|'.$date->toDateString();

        return $this->cache[$key] ??= $this->compute($branch, $date);
    }

    /** ชั่วโมงนี้ยิมเปิดอยู่และเป็นจุดเริ่มช่วงที่ถูกต้อง */
    public function isOpenSlot(Branch $branch, CarbonInterface $slotStart): bool
    {
        $slotStart = CarbonImmutable::parse($slotStart);

        foreach ($this->slotsOn($branch, $slotStart) as $slot) {
            if ($slot->equalTo($slotStart)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, CarbonImmutable> */
    protected function compute(Branch $branch, CarbonImmutable $date): array
    {
        $exceptions = $branch->scheduleExceptions()->whereDate('date', $date->toDateString())->get();

        if ($exceptions->firstWhere('type', ScheduleException::TYPE_CLOSED)) {
            return [];
        }

        $ranges = [];
        $custom = $exceptions->firstWhere('type', ScheduleException::TYPE_CUSTOM_HOURS);

        if ($custom && $custom->start_time && $custom->end_time) {
            $ranges[] = [$custom->start_time, $custom->end_time];
        } else {
            $this->templates[$branch->id] ??= $branch->scheduleTemplates()->active()->get();

            foreach ($this->templates[$branch->id] as $template) {
                if ($template->appliesOn($date)) {
                    $ranges[] = [$template->start_time, $template->end_time];
                }
            }
        }

        $special = $exceptions->firstWhere('type', ScheduleException::TYPE_SPECIAL_OPEN);

        if ($special && $special->start_time && $special->end_time) {
            $ranges[] = [$special->start_time, $special->end_time];
        }

        $slots = [];

        foreach ($ranges as [$from, $to]) {
            $cursor = $this->at($date, $from);
            $limit = $this->at($date, $to);

            // ช่วงที่ข้ามเที่ยงคืน เช่น 22:00 - 02:00
            if ($limit->lte($cursor)) {
                $limit = $limit->addDay();
            }

            // ช่วงสุดท้ายที่ไม่ครบชั่วโมงถูกตัดทิ้ง
            while ($cursor->addMinutes(self::SLOT_MINUTES)->lte($limit)) {
                $slots[$cursor->format('Y-m-d H:i')] = $cursor;
                $cursor = $cursor->addMinutes(self::SLOT_MINUTES);
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    protected function at(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return $date->setTime((int) $h, (int) $m);
    }
}
