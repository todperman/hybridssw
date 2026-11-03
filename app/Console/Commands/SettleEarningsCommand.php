<?php

namespace App\Console\Commands;

use App\Services\TrainerEarningService;
use Illuminate\Console\Command;

class SettleEarningsCommand extends Command
{
    protected $signature = 'earnings:settle';

    protected $description = 'ย้ายส่วนแบ่งเทรนเนอร์ของงานที่หมดเวลาแล้วเป็น "รอจ่าย"';

    public function handle(TrainerEarningService $earnings): int
    {
        $n = $earnings->settle();
        $this->info($n ? "รอจ่ายเพิ่ม {$n} รายการ" : 'ไม่มีรายการใหม่');

        return self::SUCCESS;
    }
}
