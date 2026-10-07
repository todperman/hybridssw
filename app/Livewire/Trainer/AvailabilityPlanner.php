<?php

namespace App\Livewire\Trainer;

use App\Exceptions\ReservationException;
use App\Models\Trainer;
use App\Services\Reservations\TrainerScheduleService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Trainer ตั้งเวลาว่างประจำสัปดาห์ เวลาว่างเฉพาะวัน และวันลา
 *
 * ไม่ต้องรอใครอนุมัติ แต่ลบเวลาว่างหรือลาทับงานที่มีคนจองไว้ไม่ได้
 * TrainerScheduleService เป็นตัวกัน หน้านี้แค่แสดงเหตุผลที่ทำไม่ได้
 */
class AvailabilityPlanner extends Component
{
    /** @var array<int, int> วันในสัปดาห์ที่เลือกตอนเพิ่มเวลาประจำ 0 = อาทิตย์ */
    public array $weekdays = [];
    public string $weeklyStart = '09:00';
    public string $weeklyEnd = '17:00';

    public string $dateOnly = '';
    public string $dateStart = '09:00';
    public string $dateEnd = '17:00';

    public string $offDate = '';
    public bool $offWholeDay = true;
    public string $offStart = '09:00';
    public string $offEnd = '12:00';
    public string $offReason = '';

    public ?string $flash = null;
    public ?string $error = null;

    #[Computed]
    public function trainer(): Trainer
    {
        return auth()->user()->trainer;
    }

    /** ตัวเลือกเวลา ทุกชั่วโมงเต็มเพราะการจองเป็นช่วงละ 1 ชั่วโมง */
    public function hourOptions(): array
    {
        return array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 24));
    }

    #[Computed]
    public function weekly()
    {
        return $this->trainer->availabilities()->whereNull('date')->orderBy('day_of_week')->orderBy('start_time')->get()->groupBy('day_of_week');
    }

    #[Computed]
    public function dated()
    {
        return $this->trainer->availabilities()->whereNotNull('date')->where('date', '>=', today())->orderBy('date')->orderBy('start_time')->get();
    }

    #[Computed]
    public function timeOffs()
    {
        return $this->trainer->timeOffs()->where('date', '>=', today())->orderBy('date')->orderBy('start_time')->get();
    }

    public function toggleAccepting(): void
    {
        $this->trainer->update(['accepts_bookings' => ! $this->trainer->accepts_bookings]);
        unset($this->trainer);

        $this->flash = $this->trainer->accepts_bookings
            ? 'เปิดรับงานแล้ว ลูกเทรนเลือกคุณได้ตามเวลาว่างที่ตั้งไว้'
            : 'หยุดรับงานใหม่แล้ว งานที่มีคนจองไว้ยังเป็นของคุณ';
    }

    public function addWeekly(TrainerScheduleService $schedule): void
    {
        $this->reset('flash', 'error');

        if ($this->weekdays === []) {
            $this->error = 'เลือกวันอย่างน้อยหนึ่งวัน';

            return;
        }

        $this->attempt(function () use ($schedule) {
            DB::transaction(function () use ($schedule) {
                foreach (array_unique(array_map('intval', $this->weekdays)) as $day) {
                    $schedule->addWindow($this->trainer, $day, null, $this->weeklyStart, $this->weeklyEnd);
                }
            });
            $this->weekdays = [];
            $this->flash = 'เพิ่มเวลาว่างประจำสัปดาห์แล้ว';
        });
    }

    public function addDated(TrainerScheduleService $schedule): void
    {
        $this->reset('flash', 'error');

        if ($this->dateOnly === '') {
            $this->error = 'เลือกวันที่ก่อน';

            return;
        }

        $this->attempt(function () use ($schedule) {
            $schedule->addWindow($this->trainer, null, $this->dateOnly, $this->dateStart, $this->dateEnd);
            $this->dateOnly = '';
            $this->flash = 'เพิ่มเวลาว่างเฉพาะวันแล้ว';
        });
    }

    public function addTimeOff(TrainerScheduleService $schedule): void
    {
        $this->reset('flash', 'error');

        if ($this->offDate === '' || $this->offDate < today()->toDateString()) {
            $this->error = 'เลือกวันลาที่ยังไม่ผ่านไป';

            return;
        }

        $this->attempt(function () use ($schedule) {
            $schedule->addTimeOff(
                $this->trainer,
                $this->offDate,
                $this->offWholeDay ? null : $this->offStart,
                $this->offWholeDay ? null : $this->offEnd,
                trim($this->offReason) ?: null,
            );
            $this->reset('offDate', 'offReason');
            $this->flash = 'บันทึกวันลาแล้ว';
        });
    }

    public function removeWindow(int $id, TrainerScheduleService $schedule): void
    {
        $this->reset('flash', 'error');
        $window = $this->trainer->availabilities()->findOrFail($id);

        $this->attempt(fn () => $schedule->removeWindow($window));
    }

    public function removeTimeOff(int $id, TrainerScheduleService $schedule): void
    {
        $this->reset('flash', 'error');
        $schedule->removeTimeOff($this->trainer->timeOffs()->findOrFail($id));
        unset($this->timeOffs);
    }

    protected function attempt(\Closure $action): void
    {
        try {
            $action();
        } catch (ReservationException $e) {
            $this->error = $e->getMessage();
        }

        unset($this->weekly, $this->dated, $this->timeOffs);
    }

    public function render()
    {
        return view('livewire.trainer.availability-planner')->layout('layouts.app');
    }
}
