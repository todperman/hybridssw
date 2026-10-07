<?php

namespace Tests\Feature;

use App\Livewire\UpcomingReminder;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

class UpcomingReminderTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.reminder.lead_minutes' => 90]);
        Notification::fake();
    }

    /** จองตอน 09:00 ให้เริ่ม 18:00 แล้วข้ามเวลาไปจนเหลือ $minutes นาทีก่อนเริ่ม */
    protected function confirmedReservationIn(int $minutes, bool $confirm = true): Reservation
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));

        $gym = $this->makeGym();
        $friend = $this->makeMember($gym);
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [$friend], $this->makeAvailableTrainer($gym), CarbonImmutable::parse('2026-10-07 18:00'));

        if ($confirm) {
            $this->confirmManually($r);
        }

        $this->travelTo($r->starts_at->subMinutes($minutes));

        return $r->refresh();
    }

    #[Test]
    public function every_participant_and_the_trainer_are_reminded_when_it_is_close(): void
    {
        $r = $this->confirmedReservationIn(45);

        foreach ($r->participants as $member) {
            Livewire::actingAs($member->user)->test(UpcomingReminder::class)->assertSee($r->timeLabel());
        }

        Livewire::actingAs($r->trainer->user)->test(UpcomingReminder::class)
            ->assertSee($r->timeLabel())
            ->assertSee(route('reservations.show', $r->reference));
    }

    #[Test]
    public function nothing_shows_when_it_is_still_far_away(): void
    {
        $r = $this->confirmedReservationIn(60 * 5);

        Livewire::actingAs($r->payer->user)->test(UpcomingReminder::class)->assertDontSee($r->timeLabel());
    }

    #[Test]
    public function unpaid_bookings_are_not_reminded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 17:20'));
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), CarbonImmutable::parse('2026-10-07 18:00'));

        Livewire::actingAs($r->payer->user)->test(UpcomingReminder::class)->assertDontSee($r->timeLabel());
    }

    #[Test]
    public function a_dismissed_reminder_stays_closed(): void
    {
        $r = $this->confirmedReservationIn(30);

        Livewire::actingAs($r->payer->user)->test(UpcomingReminder::class)
            ->call('dismiss', $r->id)
            ->assertDontSee($r->timeLabel());
    }

    #[Test]
    public function strangers_see_nothing(): void
    {
        $r = $this->confirmedReservationIn(30);

        Livewire::actingAs($this->makeMember($r->branch)->user)->test(UpcomingReminder::class)->assertDontSee($r->timeLabel());
    }
}
