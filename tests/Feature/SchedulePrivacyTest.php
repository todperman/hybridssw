<?php

namespace Tests\Feature;

use App\Enums\SessionMode;
use App\Livewire\Trainer\BookingBoard;
use App\Models\WorkoutSession;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class SchedulePrivacyTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    /** @return array{0: \App\Models\Trainer, 1: \App\Models\Trainer, 2: WorkoutSession} */
    protected function twoTrainersSharingASession(array $sessionAttributes = []): array
    {
        // ตรึงเวลาไว้ตอนเช้า แล้ววางรอบไว้ตอนเย็นของวันเดียวกัน
        $this->travelTo(now()->setTime(8, 0));

        $branch = $this->makeBranch(['default_capacity' => 5]);
        $mine = $this->makeTrainer($branch);
        $other = $this->makeTrainer($branch);

        $session = $this->makeSession($branch, array_merge([
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(19, 0),
        ], $sessionAttributes));

        return [$mine, $other, $session];
    }

    #[Test]
    public function a_trainer_sees_their_own_members_by_name(): void
    {
        [$mine, , $session] = $this->twoTrainersSharingASession();

        $myMember = $this->makeMember($session->branch, $mine);
        $myMember->user->update(['first_name' => 'ลูกทีม', 'last_name' => 'ของฉัน']);

        app(BookingService::class)->book($session, $myMember, $mine);

        Livewire::actingAs($mine->user)
            ->test(BookingBoard::class)
            ->assertSee('ลูกทีม ของฉัน')
            ->assertSee('ลูกทีมของคุณ');
    }

    #[Test]
    public function other_peoples_bookings_are_visible_but_never_named(): void
    {
        [$mine, $other, $session] = $this->twoTrainersSharingASession();

        $theirMember = $this->makeMember($session->branch, $other);
        $theirMember->user->update(['first_name' => 'ลูกทีม', 'last_name' => 'คนอื่น']);
        $other->user->update(['first_name' => 'เทรนเนอร์', 'last_name' => 'คนอื่น']);

        app(BookingService::class)->book($session, $theirMember, $other);

        Livewire::actingAs($mine->user)
            ->test(BookingBoard::class)
            // เห็นว่าที่นั่งถูกใช้ไปแล้ว
            ->assertSee('ผู้เล่นอื่น')
            ->assertSee('ว่าง 4/5')
            // แต่ต้องไม่หลุดว่าเป็นของใคร
            ->assertDontSee('ลูกทีม คนอื่น')
            ->assertDontSee('เทรนเนอร์ คนอื่น');
    }

    #[Test]
    public function an_exclusive_session_does_not_reveal_who_claimed_it(): void
    {
        [$mine, $other, $session] = $this->twoTrainersSharingASession(['mode' => SessionMode::Exclusive]);

        $other->user->update(['first_name' => 'ครู', 'last_name' => 'เหมารอบ']);
        $theirMember = $this->makeMember($session->branch, $other);

        app(BookingService::class)->book($session, $theirMember, $other);

        Livewire::actingAs($mine->user)
            ->test(BookingBoard::class)
            ->assertSee('ถูกเทรนเนอร์ท่านอื่นเหมาไปแล้ว')
            ->assertDontSee('ครู เหมารอบ');
    }

    #[Test]
    public function the_seat_bar_separates_my_seats_from_other_peoples(): void
    {
        [$mine, $other, $session] = $this->twoTrainersSharingASession();

        $bookings = app(BookingService::class);
        $bookings->book($session, $this->makeMember($session->branch, $mine), $mine);
        $bookings->book($session, $this->makeMember($session->branch, $other), $other);
        $bookings->book($session, $this->makeMember($session->branch, $other), $other);

        Livewire::actingAs($mine->user)
            ->test(BookingBoard::class)
            ->assertSee('ของคุณ 1')
            ->assertSee('ผู้เล่นอื่น 2')
            ->assertSee('ว่าง 2');
    }
}
