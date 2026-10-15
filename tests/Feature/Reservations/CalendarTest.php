<?php

namespace Tests\Feature\Reservations;

use App\Livewire\Reservations\AvailabilityCalendar;
use App\Livewire\Reservations\BookingWizard;
use App\Services\Reservations\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ปฏิทินความว่าง: สรุปรายวัน รายชั่วโมง ความเป็นส่วนตัว และกดจองจากช่องว่าง */
class CalendarTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00'));
        Notification::fake();
    }

    #[Test]
    public function the_month_summary_counts_free_hours_and_marks_closed_past_and_out_of_window_days(): void
    {
        $gym = $this->makeGym(['booking_window_days' => 10]); // เปิด 06:00–22:00 = 16 ชม.
        $gym->scheduleExceptions()->create(['date' => '2026-10-20', 'type' => 'closed']);
        $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), CarbonImmutable::parse('2026-10-16 18:00'), hours: 2));

        $days = app(CalendarService::class)->month($gym, CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(14, $days['2026-10-16']['free']);
        $this->assertSame(16, $days['2026-10-16']['open']);
        $this->assertTrue($days['2026-10-20']['closed']);
        $this->assertTrue($days['2026-10-14']['past']);
        $this->assertTrue($days['2026-10-26']['beyond']);
        $this->assertFalse($days['2026-10-25']['beyond']);
        // วันนี้ 09:00 ช่อง 06:00–09:00 เริ่มไปแล้ว (ช่อง 09:00 ด้วย) เหลือ 10:00–21:00 = 12
        $this->assertSame(12, $days['2026-10-15']['free']);
    }

    #[Test]
    public function the_day_view_shows_mine_taken_and_free_with_trainer_counts_and_runs(): void
    {
        $gym = $this->makeGym();
        $t1 = $this->makeAvailableTrainer($gym);
        $t2 = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $other = $this->makeMember($gym);

        $this->confirmManually($this->bookAsTrainee($gym, $me, [], $t1, CarbonImmutable::parse('2026-10-16 10:00')));
        $this->confirmManually($this->bookAsTrainee($gym, $other, [], $t2, CarbonImmutable::parse('2026-10-16 13:00')));

        $slots = collect(app(CalendarService::class)->day($gym, CarbonImmutable::parse('2026-10-16'), $me->user))->keyBy(fn ($s) => $s['start']->format('H:i'));

        $this->assertSame('mine', $slots['10:00']['status']);
        $this->assertNotNull($slots['10:00']['reference']);
        $this->assertSame('taken', $slots['13:00']['status']);
        $this->assertNull($slots['13:00']['reference'], 'ห้ามเผยการจองของคนอื่น');
        $this->assertSame('free', $slots['11:00']['status']);
        $this->assertSame(2, $slots['11:00']['trainers']);
        $this->assertSame(2, $slots['11:00']['run'], '11:00 และ 12:00 ว่าง แล้วชน 13:00');
        $this->assertSame(8, $slots['14:00']['run'], '14:00 ถึง 21:00 ว่างต่อเนื่อง 8 ช่อง');
    }

    #[Test]
    public function the_page_renders_for_both_roles_and_never_shows_other_peoples_names(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $stranger = $this->makeMember($gym);
        $stranger->user->update(['first_name' => 'คนแปลกหน้า']);
        $this->confirmManually($this->bookAsTrainee($gym, $stranger, [], $trainer, CarbonImmutable::parse('2026-10-16 18:00')));

        $me = $this->makeMember($gym);

        $this->actingAs($me->user)->get('/member/calendar?d=2026-10-16')
            ->assertOk()
            ->assertSee('จองแล้ว')
            ->assertDontSee('คนแปลกหน้า');

        $this->actingAs($trainer->user)->get('/trainer/calendar?d=2026-10-16')
            ->assertOk()
            ->assertSee('การจองของคุณ');
    }

    #[Test]
    public function picking_a_free_hour_and_a_length_hands_them_to_the_booking_page(): void
    {
        $gym = $this->makeGym();
        $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);

        Livewire::actingAs($me->user)->test(AvailabilityCalendar::class)
            ->call('selectDay', '2026-10-16')
            ->call('pick', '18:00')
            ->assertSet('picked', '18:00')
            ->call('book', '18:00', 2)
            ->assertRedirect(route('member.book', ['date' => '2026-10-16', 'start' => '18:00', 'hours' => 2]));

        $this->actingAs($me->user)->get(route('member.book', ['date' => '2026-10-16', 'start' => '18:00', 'hours' => 2]))->assertOk();

        $page = Livewire::actingAs($me->user)
            ->withQueryParams(['date' => '2026-10-16', 'start' => '18:00', 'hours' => 2])
            ->test(BookingWizard::class);

        $this->assertSame('2026-10-16', $page->instance()->date);
        $this->assertSame(2, $page->instance()->hours);
        $this->assertSame('18:00', $page->instance()->start);
    }

    #[Test]
    public function a_slot_taken_in_the_meantime_is_not_preselected(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::parse('2026-10-16 18:00'));

        $page = Livewire::actingAs($me->user)
            ->withQueryParams(['date' => '2026-10-16', 'start' => '18:00', 'hours' => 1])
            ->test(BookingWizard::class);

        $this->assertNull($page->instance()->start);
        $this->assertStringContainsString('ถูกจองไปแล้ว', $page->instance()->error);
    }

    #[Test]
    public function month_navigation_stays_inside_the_booking_window(): void
    {
        $gym = $this->makeGym(['booking_window_days' => 20]);
        $me = $this->makeMember($gym);

        Livewire::actingAs($me->user)->test(AvailabilityCalendar::class)
            ->assertSet('month', '2026-10-01')
            ->call('shiftMonth', -1)
            ->assertSet('month', '2026-10-01')
            ->call('shiftMonth', 1)
            ->assertSet('month', '2026-11-01')
            ->call('shiftMonth', 1)
            ->assertSet('month', '2026-11-01');
    }
}
