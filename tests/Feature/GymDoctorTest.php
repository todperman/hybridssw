<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

class GymDoctorTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(8, 0));
    }

    #[Test]
    public function a_branch_without_opening_hours_is_reported_as_the_reason_nothing_can_be_booked(): void
    {
        $this->makeBranch(['name' => 'สาขาทดสอบ', 'hourly_rate' => 800]);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('สาขาทดสอบ: ยังไม่มีตารางเวลาเปิด')
            ->expectsOutputToContain('7 วันข้างหน้าไม่มีชั่วโมงเปิดเลย')
            ->assertFailed();
    }

    #[Test]
    public function a_branch_without_a_price_cannot_take_bookings(): void
    {
        $this->makeGym(['name' => 'สาขาทดสอบ', 'hourly_rate' => 0]);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('สาขาทดสอบ: ยังไม่ตั้งราคาต่อชั่วโมง')
            ->assertFailed();
    }

    #[Test]
    public function opening_hours_for_only_some_days_are_called_out(): void
    {
        $branch = $this->makeBranch(['name' => 'สาขาทดสอบ', 'hourly_rate' => 800]);
        // เคยเจอจริง ตั้งชื่อว่า "จันทร์-อาทิตย์" แต่ day_of_week เป็น 0 จึงเปิดแค่วันอาทิตย์
        $this->makeTemplate($branch, ['name' => 'จันทร์-อาทิตย์', 'day_of_week' => 0]);
        $this->makeAvailableTrainer($branch);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('เปิดเฉพาะ อาทิตย์')
            ->assertSuccessful();
    }

    #[Test]
    public function no_trainer_with_available_hours_means_trainees_have_nobody_to_pick(): void
    {
        $gym = $this->makeGym();
        $this->makeTrainer($gym);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('ไม่มี Trainer ที่เปิดรับงานและตั้งเวลาว่างไว้')
            ->assertFailed();
    }

    #[Test]
    public function a_ready_system_passes(): void
    {
        $gym = $this->makeGym(['payment_instructions' => 'โอนเข้าบัญชีทดสอบ']);
        $this->makeAvailableTrainer($gym);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('เปิดครบ 7 วัน')
            ->expectsOutputToContain('Trainer พร้อมรับงาน 1 คน')
            ->expectsOutputToContain('พร้อมให้จอง')
            ->assertSuccessful();
    }

    #[Test]
    public function holds_that_never_expired_point_at_a_missing_scheduler(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $gym = $this->makeGym();
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), now()->addDay()->setTime(18, 0));

        $this->travel(2)->hours();

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('ตัวตั้งเวลาอาจไม่ได้ทำงาน');
    }

    #[Test]
    public function omise_without_keys_is_a_failure(): void
    {
        config(['gym.payments.driver' => 'omise', 'services.omise.public_key' => null, 'services.omise.secret_key' => null]);
        $this->makeAvailableTrainer($this->makeGym());

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('ยังไม่ได้ใส่คีย์')
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
