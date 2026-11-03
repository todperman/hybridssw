<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\TrainerEarningService;
use Illuminate\Console\Command;

/** สร้างแถวส่วนแบ่งให้การจองที่ยืนยันไว้ก่อนเปิดใช้ระบบส่วนแบ่ง รันซ้ำได้ ไม่แตะแถวที่จ่ายแล้ว */
class BackfillEarningsCommand extends Command
{
    protected $signature = 'earnings:backfill {--from= : นับเฉพาะการจองที่เริ่มตั้งแต่วันนี้ (Y-m-d)}';

    protected $description = 'คำนวณส่วนแบ่งเทรนเนอร์ย้อนหลังให้การจองที่ยืนยันแล้วแต่ยังไม่มีแถวส่วนแบ่ง';

    public function handle(TrainerEarningService $earnings): int
    {
        $query = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereNotNull('trainer_id')
            ->whereDoesntHave('earning')
            ->when($this->option('from'), fn ($q, $from) => $q->where('starts_at', '>=', $from));

        $n = 0;
        $query->with('trainer', 'branch')->orderBy('starts_at')->each(function (Reservation $r) use ($earnings, &$n) {
            if ($earnings->sync($r)) {
                $n++;
            }
        });

        $settled = $earnings->settle();
        $this->info("สร้างส่วนแบ่ง {$n} รายการ · รอจ่าย {$settled} รายการ");

        return self::SUCCESS;
    }
}
