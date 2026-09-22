<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MemberStatus;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Exceptions\BookingException;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class BookingEligibilityTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookings = app(BookingService::class);
    }

    #[Test]
    public function a_trainer_awaiting_approval_cannot_book(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, [
            'type' => TrainerType::External,
            'status' => TrainerStatus::Pending,
            'approved_at' => null,
        ]);
        $member = $this->makeMember($branch, $trainer);

        $this->expectExceptionMessage('ยังไม่ได้รับอนุมัติ');

        $this->bookings->book($this->makeSession($branch), $member, $trainer);
    }

    #[Test]
    public function an_external_trainer_with_an_expired_certificate_cannot_book(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, [
            'type' => TrainerType::External,
            'certification_name' => 'ACE CPT',
            'certification_expires_at' => now()->subDay(),
        ]);
        $member = $this->makeMember($branch, $trainer);

        $this->expectExceptionMessage('ใบรับรองของเทรนเนอร์หมดอายุ');

        $this->bookings->book($this->makeSession($branch), $member, $trainer);
    }

    #[Test]
    public function an_internal_trainer_is_not_blocked_by_certificate_rules(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, [
            'type' => TrainerType::Internal,
            'certification_expires_at' => now()->subYear(),
        ]);
        $member = $this->makeMember($branch, $trainer);

        $booking = $this->bookings->book($this->makeSession($branch), $member, $trainer);

        $this->assertSame(BookingStatus::Booked, $booking->status);
    }

    #[Test]
    public function the_seat_quota_per_session_comes_from_config_and_is_enforced(): void
    {
        // ทดสอบกลไกการจำกัด ไม่ผูกกับตัวเลขใด เพราะค่านี้ตั้งได้ที่ config/gym.php
        config(['gym.trainer_defaults.external.seats_per_session' => 3]);

        $branch = $this->makeBranch(['default_capacity' => 5]);
        $trainer = $this->makeTrainer($branch, ['type' => TrainerType::External]);
        $session = $this->makeSession($branch);

        $this->assertSame(3, $trainer->maxSeatsPerSession());

        for ($i = 0; $i < 3; $i++) {
            $this->bookings->book($session, $this->makeMember($branch, $trainer), $trainer);
        }

        $this->expectExceptionMessage('จองได้สูงสุด 3 ที่นั่งต่อรอบ');

        $this->bookings->book($session, $this->makeMember($branch, $trainer), $trainer, allowWaitlist: false);
    }

    #[Test]
    public function an_admin_override_beats_the_configured_quota(): void
    {
        config(['gym.trainer_defaults.external.seats_per_session' => 3]);

        $branch = $this->makeBranch(['default_capacity' => 5]);
        $trainer = $this->makeTrainer($branch, [
            'type' => TrainerType::External,
            'max_seats_per_session' => 5,
        ]);

        $this->assertSame(5, $trainer->maxSeatsPerSession());
    }

    #[Test]
    public function an_external_trainer_cannot_book_beyond_seven_days_ahead(): void
    {
        config(['gym.trainer_defaults.external.advance_booking_days' => 7]);

        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['type' => TrainerType::External]);
        $member = $this->makeMember($branch, $trainer);
        $session = $this->makeSession($branch, ['starts_at' => now()->addDays(20)->setTime(18, 0)]);

        $this->expectExceptionMessage('จองล่วงหน้าได้ไม่เกิน 7 วัน');

        $this->bookings->book($session, $member, $trainer);
    }

    #[Test]
    public function an_internal_trainer_may_book_thirty_days_ahead(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['type' => TrainerType::Internal]);
        $member = $this->makeMember($branch, $trainer);
        $session = $this->makeSession($branch, ['starts_at' => now()->addDays(20)->setTime(18, 0)]);

        $this->assertSame(BookingStatus::Booked, $this->bookings->book($session, $member, $trainer)->status);
    }

    #[Test]
    public function a_trainer_cannot_book_someone_outside_their_team(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $stranger = $this->makeMember($branch, null);

        $this->expectExceptionMessage('ไม่ได้อยู่ในทีมของคุณ');

        $this->bookings->book($this->makeSession($branch), $stranger, $trainer);
    }

    #[Test]
    public function a_member_without_a_health_form_on_file_can_still_be_booked(): void
    {
        // เดิมระบบบล็อกการจองถ้ายังไม่เซ็น PAR-Q ตอนนี้ตัดเงื่อนไขนั้นออกแล้ว
        // เทสต์นี้กันไม่ให้เผลอเอากลับมาโดยไม่ตั้งใจ
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer, ['parq_signed_at' => null]);

        $booking = $this->bookings->book($this->makeSession($branch), $member, $trainer);

        $this->assertNotNull($booking->id);
    }

    #[Test]
    public function a_suspended_member_cannot_be_booked(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer, [
            'status' => MemberStatus::Suspended,
            'suspended_until' => now()->addDays(3),
        ]);

        $this->expectExceptionMessage('ถูกระงับสิทธิ์จอง');

        $this->bookings->book($this->makeSession($branch), $member, $trainer);
    }

    #[Test]
    public function a_member_without_credits_cannot_be_booked(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer, credits: 0);

        $this->expectExceptionMessage('เครดิตคงเหลือไม่พอ');

        $this->bookings->book($this->makeSession($branch), $member, $trainer);
    }

    #[Test]
    public function a_closed_session_takes_no_bookings(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);
        $session = $this->makeSession($branch, ['status' => SessionStatus::Closed]);

        $this->expectExceptionMessage('ปิดรับจองแล้ว');

        $this->bookings->book($session, $member, $trainer);
    }

    #[Test]
    public function an_exclusive_session_locks_out_other_trainers(): void
    {
        $branch = $this->makeBranch();
        $first = $this->makeTrainer($branch);
        $second = $this->makeTrainer($branch);
        $session = $this->makeSession($branch, ['mode' => SessionMode::Exclusive]);

        $this->bookings->book($session, $this->makeMember($branch, $first), $first);

        $this->assertSame($first->id, $session->fresh()->claimed_by_trainer_id);

        $this->expectExceptionMessage('เหมาไปแล้ว');

        $this->bookings->book($session, $this->makeMember($branch, $second), $second);
    }

    #[Test]
    public function three_no_shows_suspend_a_member_automatically(): void
    {
        $branch = $this->makeBranch(['no_show_strike_limit' => 3, 'no_show_suspension_days' => 7]);
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer, credits: 10);

        for ($i = 1; $i <= 3; $i++) {
            $session = $this->makeSession($branch, ['starts_at' => now()->addDays($i)->setTime(18, 0)]);
            $booking = $this->bookings->book($session, $member, $trainer);
            $this->bookings->markNoShow($booking);
        }

        $member->refresh();

        $this->assertSame(3, $member->no_show_count);
        $this->assertSame(MemberStatus::Suspended, $member->status);
        $this->assertTrue($member->isSuspended());
        $this->assertTrue($member->suspended_until->isFuture());
    }

    #[Test]
    public function a_no_show_frees_the_seat_for_someone_else(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1]);
        $trainer = $this->makeTrainer($branch);
        $session = $this->makeSession($branch);

        $absent = $this->makeMember($branch, $trainer);
        $booking = $this->bookings->book($session, $absent, $trainer);

        $this->bookings->markNoShow($booking);

        // ที่นั่งถูกปล่อยผ่านการยกเลิกตามปกติ ตัวนับจึงต้องตรงกับแถวจริงเสมอ
        $session->refresh();
        $this->assertSame($session->activeBookings()->count(), $session->booked_count);
    }

    #[Test]
    public function a_trainer_cannot_book_into_another_branch(): void
    {
        $home = $this->makeBranch();
        $other = $this->makeBranch();

        $trainer = $this->makeTrainer($home);
        $member = $this->makeMember($home, $trainer);

        $this->expectExceptionMessage('สาขาเดียวกับรอบ');

        $this->bookings->book($this->makeSession($other), $member, $trainer);
    }
}
