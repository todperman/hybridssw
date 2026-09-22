<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MemberStatus;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class BookingRulesTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookings = app(BookingService::class);
    }

    #[Test]
    public function it_fills_a_session_up_to_capacity_then_waitlists(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 5]);
        $trainer = $this->makeTrainer($branch);
        $session = $this->makeSession($branch);

        $statuses = [];

        for ($i = 0; $i < 6; $i++) {
            $member = $this->makeMember($branch, $trainer);
            $statuses[] = $this->bookings->book($session, $member, $trainer)->status;
        }

        $session->refresh();

        $this->assertSame(5, $session->booked_count);
        $this->assertSame(1, $session->waitlist_count);
        $this->assertSame(BookingStatus::Waitlisted, $statuses[5]);
        $this->assertSame(5, $session->activeBookings()->count(), 'ตัวนับต้องตรงกับแถวจริงเสมอ');
    }

    #[Test]
    public function it_rejects_the_sixth_booking_when_waitlist_is_off(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 5]);
        $trainer = $this->makeTrainer($branch);
        $session = $this->makeSession($branch);

        for ($i = 0; $i < 5; $i++) {
            $this->bookings->book($session, $this->makeMember($branch, $trainer), $trainer);
        }

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('รอบนี้เต็มแล้ว');

        $this->bookings->book($session, $this->makeMember($branch, $trainer), $trainer, allowWaitlist: false);
    }

    #[Test]
    public function the_unique_index_stops_a_double_booking_that_slips_past_validation(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);
        $session = $this->makeSession($branch);

        $this->bookings->book($session, $member, $trainer);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('จองรอบนี้ไว้แล้ว');

        $this->bookings->book($session, $member, $trainer);
    }

    #[Test]
    public function a_cancelled_member_can_book_the_same_session_again(): void
    {
        // active_member_key ถูกตั้งเป็น NULL ตอนยกเลิก คีย์ unique จึงไม่บล็อกการจองใหม่
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);
        $session = $this->makeSession($branch);

        $first = $this->bookings->book($session, $member, $trainer);
        $this->bookings->cancel($first);

        $second = $this->bookings->book($session, $member, $trainer);

        $this->assertSame(BookingStatus::Booked, $second->status);
        $this->assertSame(1, $session->fresh()->booked_count);
    }

    #[Test]
    public function cancelling_early_refunds_the_credit_and_promotes_the_waitlist(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1, 'cancellation_cutoff_hours' => 4]);
        $trainer = $this->makeTrainer($branch);
        $session = $this->makeSession($branch, ['starts_at' => now()->addDays(2)->setTime(18, 0)]);

        $seated = $this->makeMember($branch, $trainer);
        $queued = $this->makeMember($branch, $trainer);

        $seatedBooking = $this->bookings->book($session, $seated, $trainer);
        $queuedBooking = $this->bookings->book($session, $queued, $trainer);

        $this->assertSame(BookingStatus::Waitlisted, $queuedBooking->status);
        $this->assertSame(9, $seated->fresh()->availableCredits());
        $this->assertSame(10, $queued->fresh()->availableCredits(), 'คิวสำรองต้องยังไม่ถูกตัดเครดิต');

        $this->bookings->cancel($seatedBooking);

        $this->assertSame(10, $seated->fresh()->availableCredits(), 'ยกเลิกทันเวลาต้องได้เครดิตคืน');
        $this->assertFalse($seatedBooking->fresh()->cancelled_late);

        $promoted = $queuedBooking->fresh();
        $this->assertSame(BookingStatus::Booked, $promoted->status);
        $this->assertNotNull($promoted->confirm_deadline_at);
        $this->assertSame(9, $queued->fresh()->availableCredits(), 'ตัดเครดิตตอนได้ที่นั่งจริง');

        $session->refresh();
        $this->assertSame(1, $session->booked_count);
        $this->assertSame(0, $session->waitlist_count);
    }

    #[Test]
    public function cancelling_after_the_cutoff_keeps_the_credit_spent(): void
    {
        $branch = $this->makeBranch(['cancellation_cutoff_hours' => 4]);
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);

        // เหลืออีก 2 ชั่วโมงจะเริ่ม ซึ่งเลยกำหนดยกเลิกฟรีแล้ว
        $session = $this->makeSession($branch, ['starts_at' => now()->addHours(2)]);

        $booking = $this->bookings->book($session, $member, $trainer);
        $this->bookings->cancel($booking);

        $this->assertTrue($booking->fresh()->cancelled_late);
        $this->assertSame(9, $member->fresh()->availableCredits());
    }

    #[Test]
    public function a_member_cannot_be_booked_into_two_sessions_at_the_same_hour(): void
    {
        $branch = $this->makeBranch();
        $trainerA = $this->makeTrainer($branch);
        $trainerB = $this->makeTrainer($branch);

        $member = $this->makeMember($branch, $trainerA);
        $member->trainers()->attach($trainerB->id, ['status' => 'active', 'joined_at' => now()]);

        $at = now()->addDay()->setTime(18, 0);
        $first = $this->makeSession($branch, ['starts_at' => $at]);

        // รอบคนละแถวแต่เวลาทับกัน ต้องถูกบล็อก
        $overlapping = $this->makeSession($branch, [
            'starts_at' => $at->copy()->addMinutes(30),
            'ends_at' => $at->copy()->addMinutes(90),
        ]);

        $this->bookings->book($first, $member, $trainerA);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('มีคิวชนกัน');

        $this->bookings->book($overlapping, $member, $trainerB);
    }
}
