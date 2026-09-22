<?php

namespace Tests\Feature;

use App\Livewire\Trainer\BookingBoard;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class ScheduleFilterTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    #[Test]
    public function the_filter_keeps_only_rounds_that_hold_my_members(): void
    {
        $this->travelTo(now()->setTime(8, 0));

        $branch = $this->makeBranch();
        $mine = $this->makeTrainer($branch);
        $other = $this->makeTrainer($branch);

        $withMine = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0), 'ends_at' => now()->setTime(19, 0)]);
        $theirsOnly = $this->makeSession($branch, ['starts_at' => now()->setTime(19, 0), 'ends_at' => now()->setTime(20, 0)]);
        $this->makeSession($branch, ['starts_at' => now()->setTime(20, 0), 'ends_at' => now()->setTime(21, 0)]);

        $bookings = app(BookingService::class);
        $bookings->book($withMine, $this->makeMember($branch, $mine), $mine);
        $bookings->book($theirsOnly, $this->makeMember($branch, $other), $other);

        $component = Livewire::actingAs($mine->user)->test(BookingBoard::class);

        $this->assertSame(3, $component->instance()->sessions->count());

        $component->call('toggleOnlyMine');

        $filtered = $component->instance()->sessions;

        $this->assertSame(1, $filtered->count());
        $this->assertSame($withMine->id, $filtered->first()->id);
    }

    #[Test]
    public function the_filter_also_applies_to_the_week_grid(): void
    {
        $this->travelTo(now()->setTime(8, 0));

        $branch = $this->makeBranch();
        $mine = $this->makeTrainer($branch);
        $other = $this->makeTrainer($branch);

        $withMine = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0), 'ends_at' => now()->setTime(19, 0)]);
        $theirs = $this->makeSession($branch, ['starts_at' => now()->setTime(19, 0), 'ends_at' => now()->setTime(20, 0)]);

        $bookings = app(BookingService::class);
        $bookings->book($withMine, $this->makeMember($branch, $mine), $mine);
        $bookings->book($theirs, $this->makeMember($branch, $other), $other);

        $component = Livewire::actingAs($mine->user)->test(BookingBoard::class)->call('toggleOnlyMine');

        // เหลือแค่ชั่วโมงเดียวคือรอบที่ลูกทีมเราอยู่
        $this->assertSame([18], $component->instance()->weekGrid['hours']);
    }

    #[Test]
    public function a_cancelled_booking_no_longer_keeps_the_round_in_the_filter(): void
    {
        $this->travelTo(now()->setTime(8, 0));

        $branch = $this->makeBranch();
        $mine = $this->makeTrainer($branch);
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0), 'ends_at' => now()->setTime(19, 0)]);

        $bookings = app(BookingService::class);
        $booking = $bookings->book($session, $this->makeMember($branch, $mine), $mine);

        $component = Livewire::actingAs($mine->user)->test(BookingBoard::class)->call('toggleOnlyMine');
        $this->assertSame(1, $component->instance()->sessions->count());

        $bookings->cancel($booking);

        $component->call('$refresh');
        $this->assertSame(0, $component->instance()->sessions->count());
    }
}
