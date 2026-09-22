<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\SessionGenerator;
use Illuminate\Console\Command;

/**
 * เลื่อนขอบเขตการจองไปข้างหน้าทุกวัน
 * ถ้าไม่มีงานนี้ รอบจะหมดเมื่อถึงวันสุดท้ายที่เคยสร้างไว้
 */
class GenerateSessionsCommand extends Command
{
    protected $signature = 'sessions:generate
        {--branch= : รหัสสาขาที่ต้องการ (เว้นว่าง = ทุกสาขาที่เปิดใช้งาน)}
        {--days= : จำนวนวันล่วงหน้า (เว้นว่าง = ใช้ค่าของสาขา)}';

    protected $description = 'สร้างรอบล่วงหน้าจากกฎเวลาเปิดของแต่ละสาขา';

    public function handle(SessionGenerator $generator): int
    {
        $query = Branch::query()->active();

        if ($code = $this->option('branch')) {
            $query->where('code', $code);
        }

        $branches = $query->get();

        if ($branches->isEmpty()) {
            $this->warn('ไม่พบสาขาที่ตรงเงื่อนไข');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($branches as $branch) {
            $days = (int) ($this->option('days') ?: $branch->session_horizon_days);

            $result = $generator->generateForBranch($branch, now(), now()->addDays($days));

            $rows[] = [$branch->code, $branch->name, $days, $result['created'], $result['skipped']];
        }

        $this->table(['รหัส', 'สาขา', 'ล่วงหน้า (วัน)', 'สร้างใหม่', 'มีอยู่แล้ว'], $rows);

        return self::SUCCESS;
    }
}
