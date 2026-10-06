<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class GymDoctorTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(8, 0));
    }

    #[Test]
    public function a_branch_without_opening_hours_is_reported_as_the_reason_nothing_can_be_booked(): void
    {
        $this->makeBranch(['name' => 'สาขาทดสอบ']);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('สาขาทดสอบ: ยังไม่มีตารางเวลาเปิด')
            ->expectsOutputToContain('ไม่มีรอบเลย')
            ->assertFailed();
    }

    #[Test]
    public function opening_hours_for_only_some_days_are_called_out(): void
    {
        $branch = $this->makeBranch(['name' => 'สาขาทดสอบ']);
        // เคยเจอจริง ตั้งชื่อว่า "จันทร์-อาทิตย์" แต่ day_of_week เป็น 0 จึงเปิดแค่วันอาทิตย์
        $this->makeTemplate($branch, ['name' => 'จันทร์-อาทิตย์', 'day_of_week' => 0]);
        $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('เปิดเฉพาะ อาทิตย์')
            ->assertSuccessful();
    }

    #[Test]
    public function a_ready_system_passes(): void
    {
        $branch = $this->makeBranch();

        foreach (range(0, 6) as $day) {
            $this->makeTemplate($branch, ['day_of_week' => $day]);
        }

        $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $this->makeSession($branch, ['starts_at' => now()->addDays(10)->setTime(18, 0)]);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('เปิดครบ 7 วัน')
            ->expectsOutputToContain('สมาชิกจองเองได้ 1 รอบ')
            ->expectsOutputToContain('พร้อมให้จอง')
            ->assertSuccessful();
    }

    #[Test]
    public function full_or_exclusive_sessions_do_not_count_as_bookable(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1]);
        $this->makeTemplate($branch);
        $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0), 'booked_count' => 1]);
        $this->makeSession($branch, ['starts_at' => now()->setTime(19, 0), 'mode' => \App\Enums\SessionMode::Exclusive]);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('ไม่มีรอบที่สมาชิกจองเองได้')
            ->assertFailed();
    }

    #[Test]
    public function only_recent_errors_count_as_a_warning(): void
    {
        $log = storage_path('logs/laravel.log');
        $backup = is_file($log) ? file_get_contents($log) : null;

        try {
            file_put_contents($log, '['.now()->subDays(12)->format('Y-m-d H:i:s').'] production.ERROR: เก่าแล้ว'.PHP_EOL);
            $this->artisan('gym:doctor')->expectsOutputToContain('ไม่มีข้อผิดพลาดใหม่ใน 24 ชั่วโมง');

            file_put_contents($log, '['.now()->subMinutes(5)->format('Y-m-d H:i:s').'] production.ERROR: เพิ่งเกิด'.PHP_EOL);
            $this->artisan('gym:doctor')->expectsOutputToContain('ข้อผิดพลาดล่าสุด');
        } finally {
            $backup === null ? @unlink($log) : file_put_contents($log, $backup);
        }
    }
}
