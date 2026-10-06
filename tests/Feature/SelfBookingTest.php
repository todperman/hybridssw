<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

/**
 * สมาชิกจองให้ตัวเอง การอนุมัติโดยแอดมิน และการปิดระบบเครดิต
 */
class SelfBookingTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookings = app(BookingService::class);

        // ไฟล์นี้ทดสอบกลไกอนุมัติการจองโดยตรง ค่าเริ่มต้นของระบบตอนนี้ไม่ต้องอนุมัติแล้ว
        // จึงต้องเปิดโหมด self ไว้ เทสต์ที่ดูค่าเริ่มต้นจริงอยู่ใน MemberApprovalTest
        config(['gym.booking.approval' => 'self']);
    }

    // --- เครดิต ---

    #[Test]
    public function with_credits_off_a_trainer_can_book_a_member_who_has_no_package(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer, credits: 0);

        $booking = $this->bookings->book($this->makeSession($branch), $member, $trainer);

        $this->assertSame(BookingStatus::Booked, $booking->status);
        $this->assertFalse($booking->credit_consumed);
        $this->assertNull($booking->member_package_id);
    }

    #[Test]
    public function with_credits_on_a_self_booking_without_a_package_is_refused(): void
    {
        config(['gym.booking.require_credits' => true]);

        $branch = $this->makeBranch();
        $member = $this->makeMember($branch, credits: 0);

        $this->expectExceptionObject(BookingException::noCredits());

        $this->bookings->bookSelf($this->makeSession($branch), $member);
    }

    // --- จองเอง ---

    #[Test]
    public function a_self_booking_waits_for_approval_and_holds_its_seat(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch);
        $member = $this->makeMember($branch);

        $booking = $this->bookings->bookSelf($session, $member, $member->user);

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->trainer_id);
        $this->assertNull($booking->approved_at);
        $this->assertSame(1, $session->fresh()->booked_count, 'คำขอที่รออนุมัติต้องถือที่นั่งไว้');
    }

    #[Test]
    public function pending_requests_can_never_exceed_the_seat_count(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1]);
        $session = $this->makeSession($branch);

        $this->bookings->bookSelf($session, $this->makeMember($branch));

        try {
            $this->bookings->bookSelf($session, $this->makeMember($branch));
            $this->fail('คนที่สองต้องจองไม่ได้ เพราะที่นั่งเดียวถูกถือไว้แล้ว');
        } catch (BookingException $e) {
            $this->assertSame('session_full', $e->reason);
        }

        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(0, Booking::where('status', BookingStatus::Waitlisted->value)->count(), 'การจองเองไม่มีคิวสำรอง');
    }

    #[Test]
    public function the_same_member_cannot_request_the_same_session_twice(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch);
        $member = $this->makeMember($branch);

        $this->bookings->bookSelf($session, $member);

        $this->expectExceptionObject(BookingException::alreadyBooked());

        $this->bookings->bookSelf($session, $member);
    }

    #[Test]
    public function an_exclusive_session_is_reserved_for_trainers(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['mode' => SessionMode::Exclusive]);

        $this->expectExceptionObject(BookingException::selfBookingNotAllowed());

        $this->bookings->bookSelf($session, $this->makeMember($branch));
    }

    #[Test]
    public function members_cannot_book_beyond_the_self_booking_window(): void
    {
        config(['gym.booking.self_advance_days' => 7]);

        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->addDays(10)->setTime(18, 0)]);

        $this->expectException(BookingException::class);

        $this->bookings->bookSelf($session, $this->makeMember($branch));
    }

    // --- อนุมัติ ปฏิเสธ ถอนคำขอ ---

    #[Test]
    public function approving_confirms_the_seat_without_counting_it_again(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch);
        $admin = $this->makeTrainer($branch)->user;

        $booking = $this->bookings->bookSelf($session, $this->makeMember($branch));
        $approved = $this->bookings->approve($booking, $admin);

        $this->assertSame(BookingStatus::Booked, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame($admin->id, $approved->approved_by_user_id);
        $this->assertSame(1, $session->fresh()->booked_count);
    }

    #[Test]
    public function a_request_cannot_be_approved_after_the_session_started(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch);
        $booking = $this->bookings->bookSelf($session, $this->makeMember($branch));

        $session->update(['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(55)]);

        $this->expectExceptionObject(BookingException::sessionStarted());

        $this->bookings->approve($booking);
    }

    #[Test]
    public function rejecting_frees_the_seat_and_promotes_the_waitlist(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1]);
        $session = $this->makeSession($branch);
        $trainer = $this->makeTrainer($branch);

        $request = $this->bookings->bookSelf($session, $this->makeMember($branch));
        $waiting = $this->bookings->book($session, $this->makeMember($branch, $trainer), $trainer);
        $this->assertSame(BookingStatus::Waitlisted, $waiting->status);

        $rejected = $this->bookings->reject($request, null, 'ไม่พบการชำระเงิน');

        $this->assertSame(BookingStatus::Cancelled, $rejected->status);
        $this->assertSame('ไม่พบการชำระเงิน', $rejected->cancellation_reason);
        $this->assertNull($rejected->active_member_key);
        $this->assertSame(BookingStatus::Booked, $waiting->fresh()->status, 'ที่นั่งที่คืนมาต้องเลื่อนคิวสำรองขึ้น');
        $this->assertSame(1, $session->fresh()->booked_count);
    }

    #[Test]
    public function only_pending_requests_can_be_approved_or_rejected(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $booking = $this->bookings->book($this->makeSession($branch), $this->makeMember($branch, $trainer), $trainer);

        $this->expectExceptionObject(BookingException::notPending());

        $this->bookings->reject($booking);
    }

    #[Test]
    public function withdrawing_a_request_is_never_a_late_cancellation(): void
    {
        $branch = $this->makeBranch(['cancellation_cutoff_hours' => 24]);
        $session = $this->makeSession($branch, ['starts_at' => now()->addHours(2)]);
        $booking = $this->bookings->bookSelf($session, $this->makeMember($branch));

        $cancelled = $this->bookings->cancel($booking, null, 'ถอนคำขอ');

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertFalse($cancelled->cancelled_late, 'ยังไม่เคยได้ที่นั่งจริง จึงไม่นับเป็นยกเลิกกระชั้น');
        $this->assertSame(0, $session->fresh()->booked_count);
    }

    // --- โหมดการอนุมัติ ---

    #[Test]
    public function with_approval_off_a_self_booking_is_confirmed_immediately(): void
    {
        config(['gym.booking.approval' => 'none']);

        $branch = $this->makeBranch();
        $booking = $this->bookings->bookSelf($this->makeSession($branch), $this->makeMember($branch));

        $this->assertSame(BookingStatus::Booked, $booking->status);
        $this->assertNotNull($booking->approved_at);
    }

    #[Test]
    public function by_default_trainer_bookings_skip_approval(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);

        $booking = $this->bookings->book($this->makeSession($branch), $this->makeMember($branch, $trainer), $trainer);

        $this->assertSame(BookingStatus::Booked, $booking->status);
    }

    #[Test]
    public function approval_for_all_makes_trainer_bookings_wait_too(): void
    {
        config(['gym.booking.approval' => 'all']);

        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);

        $booking = $this->bookings->book($this->makeSession($branch), $this->makeMember($branch, $trainer), $trainer);

        $this->assertSame(BookingStatus::Pending, $booking->status);
    }

    // --- job ---

    #[Test]
    public function requests_left_unapproved_when_the_session_starts_are_closed(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch);
        $booking = $this->bookings->bookSelf($session, $this->makeMember($branch));

        $session->update(['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(55)]);

        $this->artisan('bookings:release-unconfirmed')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('ไม่ได้รับการอนุมัติก่อนรอบเริ่ม', $booking->cancellation_reason);
        $this->assertSame(0, $session->fresh()->booked_count);
    }

    #[Test]
    public function finalizing_never_counts_an_unapproved_request_as_a_no_show(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch);
        $member = $this->makeMember($branch);
        $booking = $this->bookings->bookSelf($session, $member);

        $session->update(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);

        $this->artisan('sessions:finalize')->assertSuccessful();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(0, (int) $member->fresh()->no_show_count);
    }
}
