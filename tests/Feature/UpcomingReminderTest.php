<?php

namespace Tests\Feature;

use App\Livewire\UpcomingReminder;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class UpcomingReminderTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected function bookInMinutes(int $minutes): Booking
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);
        $session = $this->makeSession($branch, ['starts_at' => now()->addMinutes($minutes)]);

        return app(\App\Services\BookingService::class)->book($session, $member, $trainer);
    }

    #[Test]
    public function a_member_is_reminded_when_their_session_is_close(): void
    {
        config(['gym.reminder.lead_minutes' => 90]);

        $booking = $this->bookInMinutes(45);

        Livewire::actingAs($booking->member->user)
            ->test(UpcomingReminder::class)
            ->assertSee($booking->workoutSession->timeLabel());
    }

    #[Test]
    public function nothing_shows_when_the_session_is_still_far_away(): void
    {
        config(['gym.reminder.lead_minutes' => 90]);

        $booking = $this->bookInMinutes(60 * 8);

        Livewire::actingAs($booking->member->user)
            ->test(UpcomingReminder::class)
            ->assertDontSee($booking->workoutSession->timeLabel());
    }

    #[Test]
    public function the_trainer_who_made_the_booking_is_reminded_too(): void
    {
        config(['gym.reminder.lead_minutes' => 90]);

        $booking = $this->bookInMinutes(30);

        Livewire::actingAs($booking->trainer->user)
            ->test(UpcomingReminder::class)
            ->assertSee($booking->workoutSession->timeLabel());
    }

    #[Test]
    public function dismissing_hides_the_reminder_for_that_booking(): void
    {
        config(['gym.reminder.lead_minutes' => 90]);

        $booking = $this->bookInMinutes(20);

        Livewire::actingAs($booking->member->user)
            ->test(UpcomingReminder::class)
            ->assertSee($booking->workoutSession->timeLabel())
            ->call('dismiss', $booking->id)
            ->assertDontSee($booking->workoutSession->timeLabel());
    }

    #[Test]
    public function a_cancelled_booking_is_never_reminded(): void
    {
        config(['gym.reminder.lead_minutes' => 90]);

        $booking = $this->bookInMinutes(30);
        app(\App\Services\BookingService::class)->cancel($booking, $booking->member->user);

        Livewire::actingAs($booking->member->user)
            ->test(UpcomingReminder::class)
            ->assertDontSee($booking->workoutSession->timeLabel());
    }
}
