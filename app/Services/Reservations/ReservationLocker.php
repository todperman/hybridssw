<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\SlotLock;

/**
 * เขียนและลบล็อกช่วงเวลาของการจอง
 *
 * ทุกการจองล็อกสามอย่างต่อชั่วโมง: ยิมทั้งหลัง Trainer และผู้เข้าร่วมทุกคน
 * ถ้ามีแถวไหนชนกับของคนอื่น unique index จะโยน QueryException ออกไป
 * ผู้เรียกต้องอยู่ใน transaction เพื่อให้ล็อกที่ใส่ไปแล้วบางส่วนถูกย้อนกลับทั้งหมด
 */
class ReservationLocker
{
    public function lock(Reservation $reservation): void
    {
        $reservation->loadMissing('participants');

        $rows = [];

        foreach ($reservation->slots() as $slot) {
            $rows[] = $this->row($reservation, SlotLock::GYM, $reservation->branch_id, $slot);

            if ($reservation->trainer_id) {
                $rows[] = $this->row($reservation, SlotLock::TRAINER, $reservation->trainer_id, $slot);
            }

            foreach ($reservation->participants as $member) {
                $rows[] = $this->row($reservation, SlotLock::MEMBER, $member->id, $slot);
            }
        }

        SlotLock::insert($rows);
    }

    /** ใส่ล็อกเฉพาะของ Trainer ใช้ตอนเปลี่ยน Trainer ที่ยิมและผู้เข้าร่วมยังล็อกอยู่ตามเดิม */
    public function lockTrainer(Reservation $reservation, int $trainerId): void
    {
        SlotLock::insert(array_map(
            fn ($slot) => $this->row($reservation, SlotLock::TRAINER, $trainerId, $slot),
            $reservation->slots(),
        ));
    }

    public function release(Reservation $reservation, ?string $resource = null): void
    {
        $reservation->locks()
            ->when($resource, fn ($q) => $q->where('resource', $resource))
            ->delete();
    }

    public static function isDuplicate(\Illuminate\Database\QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    protected function row(Reservation $reservation, string $resource, int $id, $slot): array
    {
        return [
            'reservation_id' => $reservation->id,
            'resource' => $resource,
            'resource_id' => $id,
            'slot_start' => $slot->format('Y-m-d H:i:s'),
        ];
    }
}
