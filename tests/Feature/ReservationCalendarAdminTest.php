<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ReservationCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ปฏิทินการจองในหลังบ้าน: เดือน วัน ตัวกรอง และขอบเขตสาขา */
class ReservationCalendarAdminTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00'));
        Notification::fake();
    }

    #[Test]
    public function the_calendar_shows_the_month_and_the_selected_day_with_links_to_each_reservation(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $booker = $this->makeMember($gym);
        $booker->user->update(['first_name' => 'ปฏิทิน', 'last_name' => 'ทดสอบ']);

        $confirmed = $this->confirmManually($this->bookAsTrainee($gym, $booker, [], $trainer, CarbonImmutable::parse('2026-10-16 18:00'), hours: 2));
        $pending = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::parse('2026-10-20 10:00'));

        $this->actingAs($this->makeAdmin($gym))->get('/admin/reservations-calendar?d=2026-10-16')
            ->assertOk()
            ->assertSee('ตุลาคม 2026')
            ->assertSee('ยืนยันแล้ว 1 รายการ · รอชำระ 1')
            ->assertSee($confirmed->reference)
            ->assertSee('/admin/reservations/'.$confirmed->id)
            ->assertSee('ต่อเนื่อง')
            ->assertSee('ว่าง');

        Livewire::test(ReservationCalendar::class)
            ->call('selectDay', '2026-10-20')
            ->assertSet('date', '2026-10-20')
            ->assertSee($pending->reference)
            ->assertSee('ชำระภายใน')
            ->call('shiftMonth', 1)
            ->assertSet('month', '2026-11-01')
            ->assertSee('พฤศจิกายน 2026')
            ->call('goToday')
            ->assertSet('date', '2026-10-15');
    }

    #[Test]
    public function filters_narrow_by_trainer_and_hide_closed_bookings_unless_asked(): void
    {
        $gym = $this->makeGym();
        $t1 = $this->makeAvailableTrainer($gym);
        $t2 = $this->makeAvailableTrainer($gym);
        $a = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $t1, CarbonImmutable::parse('2026-10-16 10:00')));
        $b = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $t2, CarbonImmutable::parse('2026-10-16 13:00')));
        $cancelled = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $t1, CarbonImmutable::parse('2026-10-16 16:00')));
        $this->reservations()->cancelByAdmin($cancelled, $admin = $this->makeAdmin($gym), 'ปิดปรับปรุง');

        $page = Livewire::actingAs($admin)->test(ReservationCalendar::class)->call('selectDay', '2026-10-16');

        $page->assertSee($a->reference)->assertSee($b->reference)->assertDontSee($cancelled->reference);
        $page->set('trainerId', $t1->id)->assertSee($a->reference)->assertDontSee($b->reference);
        $page->set('trainerId', null)->set('showClosed', true)->assertSee($cancelled->reference)->assertSee('ยกเลิก / หมดอายุในวันนี้');
    }

    #[Test]
    public function branch_staff_only_see_their_own_branch(): void
    {
        $gym = $this->makeGym();
        $other = $this->makeGym(['code' => 'B2', 'name' => 'สาขาสอง']);
        $foreign = $this->confirmManually($this->bookAsTrainee($other, $this->makeMember($other), [], $this->makeAvailableTrainer($other), CarbonImmutable::parse('2026-10-16 10:00')));

        $staff = $this->makeAdmin($gym);
        $staff->update(['role' => UserRole::Staff, 'branch_id' => $gym->id]);

        Livewire::actingAs($staff->fresh())->test(ReservationCalendar::class, ['branch' => $other->id])
            ->assertSet('branchId', $gym->id)
            ->call('selectDay', '2026-10-16')
            ->assertDontSee($foreign->reference)
            ->assertDontSee('สาขาสอง');

        $this->actingAs($staff->fresh())->get('/admin/reservations')->assertOk()->assertSee('ดูเป็นปฏิทิน');
    }
}
