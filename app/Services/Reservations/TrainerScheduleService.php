<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\TrainerAvailability;
use App\Models\TrainerTimeOff;
use Illuminate\Support\Facades\DB;

/**
 * Trainer กำหนดเวลาว่างและวันลา
 *
 * เพิ่มเวลาว่างได้อิสระ แต่การลบเวลาว่างหรือเพิ่มวันลาที่ไปตัดงานที่มีคนจองอยู่ทำไม่ได้
 * ต้องใช้ขั้นตอนขอเลื่อนหรือขอเปลี่ยน Trainer แทน ตามข้อกำหนดข้อ 3
 */
class TrainerScheduleService
{
    public function __construct(protected Availability $availability) {}

    public function addWindow(Trainer $trainer, ?int $dayOfWeek, ?string $date, string $start, string $end): TrainerAvailability
    {
        $this->assertRange($start, $end);

        if (($dayOfWeek === null) === ($date === null)) {
            throw new ReservationException('ระบุเป็นวันในสัปดาห์หรือวันที่ อย่างใดอย่างหนึ่ง', 'invalid_window');
        }

        if ($date !== null && now()->startOfDay()->gt($date)) {
            throw new ReservationException('เลือกวันที่ยังไม่ผ่านไป', 'invalid_window');
        }

        return $trainer->availabilities()->create([
            'day_of_week' => $dayOfWeek,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function removeWindow(TrainerAvailability $window): void
    {
        $trainer = $window->trainer;

        $this->guarded($trainer, fn () => $window->delete());
    }

    public function addTimeOff(Trainer $trainer, string $date, ?string $start = null, ?string $end = null, ?string $reason = null): TrainerTimeOff
    {
        if ($start !== null || $end !== null) {
            $this->assertRange((string) $start, (string) $end);
        }

        return $this->guarded($trainer, fn () => $trainer->timeOffs()->create([
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'reason' => $reason,
        ]));
    }

    public function removeTimeOff(TrainerTimeOff $off): void
    {
        $off->delete();
    }

    /**
     * ทำการเปลี่ยนแปลงแล้วตรวจว่างานที่มีคนจองไว้ยังอยู่ในเวลาว่างครบทุกงาน
     * ถ้าไม่ครบย้อนกลับทั้งหมด
     */
    protected function guarded(Trainer $trainer, \Closure $change): mixed
    {
        return DB::transaction(function () use ($trainer, $change) {
            $result = $change();

            // ตรวจกับเวลาว่างหลังแก้แล้ว ไม่ใช่ข้อมูลที่โหลดค้างไว้ก่อนแก้
            $trainer->unsetRelation('availabilities')->unsetRelation('timeOffs');

            $upcoming = Reservation::query()
                ->where('trainer_id', $trainer->id)
                ->whereIn('status', ReservationStatus::holding())
                ->where('ends_at', '>', now())
                ->get();

            foreach ($upcoming as $reservation) {
                if (! $this->availability->coveredBySchedule($trainer, $reservation->starts_at, $reservation->hours)) {
                    throw ReservationException::lockedByReservation();
                }
            }

            return $result;
        });
    }

    protected function assertRange(string $start, string $end): void
    {
        if (! preg_match('/^\d{2}:\d{2}/', $start) || ! preg_match('/^\d{2}:\d{2}/', $end) || substr($start, 0, 5) >= substr($end, 0, 5)) {
            throw new ReservationException('เวลาสิ้นสุดต้องหลังเวลาเริ่ม', 'invalid_window');
        }
    }
}
