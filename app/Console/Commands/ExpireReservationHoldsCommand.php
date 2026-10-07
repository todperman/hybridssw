<?php

namespace App\Console\Commands;

use App\Services\Reservations\ReservationService;
use Illuminate\Console\Command;

/** ปิดรายการรอชำระที่เลยเวลาและคืนช่วงเวลาให้คนอื่นจองได้ */
class ExpireReservationHoldsCommand extends Command
{
    protected $signature = 'reservations:expire-holds';

    protected $description = 'ปิดรายการรอชำระที่หมดเวลาและคืนช่วงเวลา';

    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireStaleHolds();

        $this->info($count > 0 ? "หมดอายุ {$count} รายการ" : 'ไม่มีรายการที่หมดเวลา');

        return self::SUCCESS;
    }
}
