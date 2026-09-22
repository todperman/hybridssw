<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ข้อมูลตั้งต้นสำหรับเซิร์ฟเวอร์จริง
 *
 * ต่างจาก DatabaseSeeder ตรงที่ไม่สร้างบัญชีตัวอย่างหรือข้อมูลทดลองใด ๆ
 * สร้างแค่สาขาแรกกับบัญชีแอดมินหนึ่งคน เพื่อให้เข้าหลังบ้านไปตั้งค่าที่เหลือเองได้
 *
 * รหัสผ่านแอดมินอ่านจาก .env ไม่ฝังไว้ในโค้ด และรันซ้ำได้โดยไม่สร้างซ้ำ
 *
 *   php artisan db:seed --class=ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->error('ต้องตั้ง ADMIN_EMAIL และ ADMIN_PASSWORD ใน .env ก่อน');
            // config ที่ถูก cache ไว้ทำให้ Laravel ไม่โหลด .env เลย env() จึงได้ค่าว่าง
            $this->command?->warn('ถ้าตั้งไว้แล้วแต่ยังขึ้นข้อความนี้ ให้รัน php artisan config:clear ก่อน');

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error('ADMIN_PASSWORD ต้องยาวอย่างน้อย 12 ตัวอักษร');

            return;
        }

        DB::transaction(function () use ($email, $password) {
            $branch = Branch::firstOrCreate(
                ['code' => env('BRANCH_CODE', 'MAIN')],
                [
                    'name' => env('BRANCH_NAME', config('app.name')),
                    'timezone' => config('app.timezone', 'Asia/Bangkok'),
                    'default_capacity' => 5,
                    'max_group_size' => 5,
                    'slot_duration_minutes' => 60,
                    'cancellation_cutoff_hours' => 6,
                    'waitlist_confirm_minutes' => 30,
                    'no_show_strike_limit' => 3,
                    'no_show_suspension_days' => 7,
                    'session_horizon_days' => 30,
                    'is_active' => true,
                ],
            );

            $admin = User::withTrashed()->firstWhere('email', $email);

            if ($admin) {
                $this->command?->warn('มีบัญชี '.$email.' อยู่แล้ว ข้ามการสร้าง');

                return;
            }

            User::create([
                'branch_id' => $branch->id,
                'first_name' => env('ADMIN_FIRST_NAME', 'ผู้ดูแล'),
                'last_name' => env('ADMIN_LAST_NAME', 'ระบบ'),
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]);

            $this->command?->info('สร้างสาขา '.$branch->name.' และบัญชีแอดมิน '.$email.' แล้ว');
            $this->command?->warn('ลบ ADMIN_PASSWORD ออกจาก .env หลังตั้งค่าเสร็จ');
        });
    }
}
