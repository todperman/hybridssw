<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Enums\RequestStatus;
use App\Enums\ReservationStatus;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Models\Trainer;
use App\Models\User;
use App\Services\DemoData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

class GymDemoTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00'));
        putenv('DEMO_PASSWORD=demo-password-123');
    }

    protected function tearDown(): void
    {
        putenv('DEMO_PASSWORD');

        parent::tearDown();
    }

    #[Test]
    public function it_creates_real_bookings_in_every_state_through_the_normal_services(): void
    {
        Mail::fake();
        $gym = $this->makeGym(['hourly_rate' => 0]);

        $this->artisan('gym:demo', ['--rate' => 900, '--force' => true])->assertSuccessful();

        $this->assertSame('900.00', (string) $gym->refresh()->hourly_rate);
        $this->assertSame(2, Trainer::count());
        $this->assertSame(6, Member::count());

        $statuses = Reservation::pluck('status')->map->value->countBy();
        $this->assertGreaterThanOrEqual(5, $statuses[ReservationStatus::Confirmed->value] ?? 0);
        $this->assertSame(1, $statuses[ReservationStatus::Expired->value] ?? 0);
        $this->assertSame(1, $statuses[ReservationStatus::Cancelled->value] ?? 0);
        $this->assertSame(1, $statuses[ReservationStatus::PendingPayment->value] ?? 0);
        $this->assertSame(RefundStatus::Succeeded, Reservation::where('status', 'cancelled')->sole()->refund_status);
        $this->assertSame(RequestStatus::Pending, ReservationRequest::sole()->status);
        $this->assertTrue(AuditLog::where('action', 'reservation.rescheduled')->exists());
        $this->assertTrue(AuditLog::where('action', 'reservation.trainer_changed')->exists());

        // ประวัติย้อนหลังต้องอยู่ในอดีตจริง และคืนนาฬิกาครบหลังสร้างเสร็จ
        $this->assertTrue(Reservation::where('starts_at', '<', now())->count() >= 4);
        $this->assertSame('2026-10-07 10:00', now()->format('Y-m-d H:i'));

        // บัญชีตัวอย่างเข้าสู่ระบบได้ด้วยรหัสที่ตั้ง ส่วนแอดมินตัวอย่างเข้าหลังบ้านไม่ได้
        $this->assertTrue(auth()->validate(['email' => 'member1@'.DemoData::DOMAIN, 'password' => 'demo-password-123']));
        $this->assertTrue(auth()->validate(['email' => 'trainer1@'.DemoData::DOMAIN, 'password' => 'demo-password-123']));
        $this->assertFalse(User::where('email', 'admin@'.DemoData::DOMAIN)->sole()->is_active);
        $this->assertFalse(auth()->validate(['email' => 'admin@'.DemoData::DOMAIN, 'password' => 'demo-password-123']));

        Mail::assertNothingOutgoing();
        $this->artisan('gym:doctor')->assertSuccessful();
    }

    #[Test]
    public function remove_deletes_every_demo_record_and_nothing_else(): void
    {
        Notification::fake();
        $gym = $this->makeGym();
        $realTrainer = $this->makeAvailableTrainer($gym);
        $real = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $realTrainer, CarbonImmutable::parse('2026-10-20 08:00'));

        $this->artisan('gym:demo', ['--force' => true])->assertSuccessful();
        $this->artisan('gym:demo', ['--force' => true])->assertFailed();
        $this->artisan('gym:demo', ['--remove' => true, '--force' => true])->assertSuccessful();

        $this->assertSame(0, User::withTrashed()->where('email', 'like', '%@'.DemoData::DOMAIN)->count());
        $this->assertSame([$real->id], Reservation::pluck('id')->all());
        $this->assertSame(1, Trainer::count());
        $this->assertSame(1, Member::count());
        $this->assertSame(0, ReservationRequest::count());
    }

    #[Test]
    public function remove_refuses_while_a_real_customer_still_relies_on_a_demo_trainer(): void
    {
        Notification::fake();
        $gym = $this->makeGym();
        $this->artisan('gym:demo', ['--force' => true, '--no-bookings' => true])->assertSuccessful();

        $demoTrainer = Trainer::whereHas('user', fn ($q) => $q->where('email', 'trainer1@'.DemoData::DOMAIN))->sole();
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $demoTrainer, CarbonImmutable::parse('2026-10-09 18:00'));

        $this->artisan('gym:demo', ['--remove' => true, '--force' => true])
            ->expectsOutputToContain('มีลูกค้าจริงจอง Trainer ตัวอย่างไว้')
            ->assertFailed();
    }

    #[Test]
    public function it_will_not_invent_opening_hours_or_a_price(): void
    {
        $this->makeBranch(['hourly_rate' => 800]);
        $this->artisan('gym:demo', ['--force' => true])->expectsOutputToContain('ยังไม่มีเวลาเปิด')->assertFailed();

        $this->makeGym(['code' => 'NOPRICE', 'hourly_rate' => 0]);
        $this->artisan('gym:demo', ['--branch' => 'NOPRICE', '--force' => true])->expectsOutputToContain('ใส่ --rate')->assertFailed();
    }

    #[Test]
    public function a_forgotten_password_can_be_reset_for_every_demo_account_except_the_admin(): void
    {
        Notification::fake();
        $this->makeGym();
        $this->artisan('gym:demo', ['--force' => true, '--no-bookings' => true])->assertSuccessful();

        putenv('DEMO_PASSWORD=another-password-456');
        $this->artisan('gym:demo', ['--reset-password' => true])
            ->expectsOutputToContain('ตั้งรหัสผ่านใหม่ให้บัญชีตัวอย่าง 8 บัญชีแล้ว')
            ->assertSuccessful();

        $this->assertTrue(auth()->validate(['email' => 'trainer1@'.DemoData::DOMAIN, 'password' => 'another-password-456']));
        $this->assertTrue(auth()->validate(['email' => 'member1@'.DemoData::DOMAIN, 'password' => 'another-password-456']));
        $this->assertFalse(auth()->validate(['email' => 'admin@'.DemoData::DOMAIN, 'password' => 'another-password-456']));
    }
}
