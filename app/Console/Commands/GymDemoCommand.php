<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\DemoData;
use App\Services\Reservations\OpeningHours;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * ใส่หรือลบข้อมูลตัวอย่างบนเซิร์ฟเวอร์จริง ไว้ลองระบบก่อนเปิดให้บริการ
 *
 *   php artisan gym:demo --rate=800      ใส่ข้อมูลตัวอย่าง (ตั้งราคาให้ถ้าสาขายังไม่มีราคา)
 *   php artisan gym:demo --remove        ลบข้อมูลตัวอย่างทั้งหมด
 *   php artisan gym:demo --reset-password ตั้งรหัสผ่านใหม่ให้บัญชีตัวอย่าง (เมื่อลืมรหัสเดิม)
 *
 * ไม่แตะเวลาเปิดของสาขา ต้องตั้งในหลังบ้านก่อน และตั้งราคาให้เฉพาะเมื่อยังเป็น 0 และสั่งมาเอง
 */
class GymDemoCommand extends Command
{
    protected $signature = 'gym:demo
        {--remove : ลบข้อมูลตัวอย่างทั้งหมด}
        {--reset-password : ตั้งรหัสผ่านใหม่ให้บัญชีตัวอย่างทุกบัญชี}
        {--branch= : รหัสสาขา (ค่าเริ่มต้นคือสาขาแรกที่เปิดใช้)}
        {--rate= : ราคาต่อชั่วโมง ใช้เฉพาะเมื่อสาขายังไม่ได้ตั้งราคา}
        {--no-bookings : สร้างแค่บัญชี ไม่สร้างการจองตัวอย่าง}
        {--force : ไม่ถามยืนยัน}';

    protected $description = 'ใส่หรือลบข้อมูลตัวอย่าง (Trainer ลูกเทรน การจองทุกสถานะ) สำหรับลองระบบ';

    public function handle(DemoData $demo, OpeningHours $hours): int
    {
        if ($this->option('remove')) {
            return $this->remove($demo);
        }

        if ($this->option('reset-password')) {
            return $this->resetPassword($demo);
        }

        if ($demo->exists()) {
            $this->error('มีข้อมูลตัวอย่างอยู่แล้ว ลบก่อนด้วย php artisan gym:demo --remove');

            return self::FAILURE;
        }

        $branch = $this->option('branch')
            ? Branch::where('code', $this->option('branch'))->first()
            : Branch::active()->orderBy('id')->first();

        if (! $branch) {
            $this->error('ไม่พบสาขา');

            return self::FAILURE;
        }

        $openHours = collect(range(0, 6))->sum(fn ($i) => count($hours->slotsOn($branch, now()->addDays($i))));

        if ($openHours === 0) {
            $this->error("{$branch->name} ยังไม่มีเวลาเปิดใน 7 วันข้างหน้า ตั้งที่หลังบ้าน → ตารางเวลาเปิด ก่อน");

            return self::FAILURE;
        }

        if ((float) $branch->hourly_rate <= 0) {
            $rate = (float) $this->option('rate');

            if ($rate <= 0) {
                $this->error("{$branch->name} ยังไม่ได้ตั้งราคาต่อชั่วโมง ตั้งในหลังบ้าน หรือใส่ --rate=800 มากับคำสั่งนี้");

                return self::FAILURE;
            }

            $branch->update(['hourly_rate' => $rate]);
            $this->warn("ตั้งราคา {$branch->name} เป็น ฿".number_format($rate, 0).'/ชม. แล้ว (ค่านี้ไม่ถูกลบตอน --remove)');
        }

        $this->line("จะสร้างข้อมูลตัวอย่างในสาขา <options=bold>{$branch->name}</> อีเมลทุกบัญชีลงท้าย @".DemoData::DOMAIN);
        $this->line('Trainer ตัวอย่างจะขึ้นให้ลูกค้าจริงเลือกได้ด้วย ลบออกก่อนเปิดให้บริการจริงด้วย --remove');

        if (! $this->option('force') && ! $this->confirm('ดำเนินการต่อ?', true)) {
            return self::SUCCESS;
        }

        [$password, $generated] = $this->password();

        if ($password === null) {
            return self::FAILURE;
        }

        // ตอนสร้างไม่ส่งอีเมล แอดมินจริงจะได้ไม่โดนเมลแจ้งเตือนจากข้อมูลตัวอย่าง
        config(['gym.notifications.mail' => false]);

        $demo->create($branch, $password, ! $this->option('no-bookings'));

        foreach ($demo->log as $line) {
            $this->line('  · '.$line);
        }

        $this->newLine();
        $this->table(['บัญชี', 'ใช้ลองอะไร'], [
            ['member1@'.DemoData::DOMAIN, 'ลูกเทรนในทีมครูต้น มีการจองที่กำลังจะถึงและประวัติย้อนหลัง'],
            ['member2@'.DemoData::DOMAIN, 'มีรายการรอชำระเงิน ลองดูหน้านับถอยหลัง'],
            ['member6@'.DemoData::DOMAIN, 'มีสิทธิ์เข้าใช้โดยไม่มี Trainer'],
            ['trainer1@'.DemoData::DOMAIN, 'ครูต้น จองให้ลูกเทรน ดูงาน ตั้งเวลาว่าง'],
            ['trainer2@'.DemoData::DOMAIN, 'ครูฝน มีคำขอยกเลิกงานรอแอดมิน'],
        ]);
        $this->line('ลูกเทรนตัวอย่างค้นด้วยเบอร์ 0990001001 ถึง 0990001006 ได้');

        $this->showPassword($password, $generated);

        return self::SUCCESS;
    }

    protected function resetPassword(DemoData $demo): int
    {
        if (! $demo->exists()) {
            $this->error('ยังไม่มีข้อมูลตัวอย่าง สร้างก่อนด้วย php artisan gym:demo');

            return self::FAILURE;
        }

        [$password, $generated] = $this->password();

        if ($password === null) {
            return self::FAILURE;
        }

        $count = $demo->resetPassword($password);
        $this->info("ตั้งรหัสผ่านใหม่ให้บัญชีตัวอย่าง {$count} บัญชีแล้ว");
        $this->showPassword($password, $generated);

        return self::SUCCESS;
    }

    /**
     * รหัสผ่านไม่ฝังในโค้ด ตั้งเองผ่าน DEMO_PASSWORD หรือให้ระบบสุ่มแล้วแสดงครั้งเดียว
     *
     * @return array{0: ?string, 1: bool}
     */
    protected function password(): array
    {
        $password = (string) env('DEMO_PASSWORD', '');

        if ($password === '') {
            return [Str::password(14, symbols: false), true];
        }

        if (strlen($password) < 12) {
            $this->error('DEMO_PASSWORD ต้องยาวอย่างน้อย 12 ตัวอักษร');

            return [null, false];
        }

        return [$password, false];
    }

    protected function showPassword(string $password, bool $generated): void
    {
        $this->newLine();

        $generated
            ? $this->warn('รหัสผ่านของทุกบัญชีตัวอย่าง (แสดงครั้งเดียว เก็บไว้เอง): '.$password)
            : $this->line('รหัสผ่านทุกบัญชีคือค่า DEMO_PASSWORD ใน .env ลบออกจาก .env ได้แล้ว');
    }

    protected function remove(DemoData $demo): int
    {
        if (! $demo->exists()) {
            $this->info('ไม่มีข้อมูลตัวอย่าง');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('ลบบัญชีและการจองตัวอย่างทั้งหมด?', true)) {
            return self::SUCCESS;
        }

        try {
            $result = $demo->remove();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("ลบบัญชีตัวอย่าง {$result['users']} บัญชี และการจองตัวอย่าง {$result['reservations']} รายการแล้ว");

        return self::SUCCESS;
    }
}
