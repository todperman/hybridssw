<?php

namespace Tests\Feature\Reservations;

use App\Enums\MemberStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Reservation;
use App\Models\SlotLock;
use App\Notifications\ReservationNotice;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ข้อ 2 ข้อ 4 ข้อ 5 และข้อ 6 ส่วนการสร้างการจองและกันช่วงเวลา */
class CreateReservationTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));
        Notification::fake();
    }

    #[Test]
    public function a_trainee_booking_holds_the_gym_the_trainer_and_everyone_for_thirty_minutes(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $booker = $this->makeMember($gym);
        $friend = $this->makeMember($gym);

        $r = $this->bookAsTrainee($gym, $booker, [$friend], $trainer, hours: 2);

        $this->assertSame(ReservationStatus::PendingPayment, $r->status);
        $this->assertSame('1600.00', (string) $r->amount, 'ราคาต่อชั่วโมงต่อการจอง x 2 ชั่วโมง');
        $this->assertTrue($r->hold_expires_at->equalTo(now()->addMinutes(30)));
        $this->assertSame($trainer->id, $r->trainer_id);

        // 2 ชั่วโมง x (ยิม 1 + Trainer 1 + สมาชิก 2)
        $this->assertSame(8, SlotLock::where('reservation_id', $r->id)->count());
        $this->assertSame(2, SlotLock::where('resource', SlotLock::GYM)->count());

        Notification::assertSentTo($booker->user, ReservationNotice::class,
            fn (ReservationNotice $n) => $n->title === 'มีรายการรอชำระเงิน');
    }

    #[Test]
    public function one_group_takes_the_whole_gym_even_with_a_single_trainee(): void
    {
        $gym = $this->makeGym();
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        try {
            $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
            $this->fail('ยิมต้องถูกปิดให้กลุ่มแรกทั้งหลัง');
        } catch (ReservationException $e) {
            $this->assertSame('gym_taken', $e->reason);
        }
    }

    #[Test]
    public function a_longer_booking_cannot_overlap_part_of_another(): void
    {
        $gym = $this->makeGym();
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), $this->tomorrowAt(19));

        $this->expectExceptionObject(ReservationException::gymTaken());

        // 18:00–20:00 ทับชั่วโมง 19:00 ของคนอื่น
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), $this->tomorrowAt(18), 2);
    }

    #[Test]
    public function the_database_itself_refuses_a_second_lock_on_the_same_slot(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        // ด่านสุดท้ายไม่ได้อยู่ที่โค้ด แต่อยู่ที่ unique index
        $this->expectException(QueryException::class);

        SlotLock::create([
            'reservation_id' => $r->id,
            'resource' => SlotLock::GYM,
            'resource_id' => $gym->id,
            'slot_start' => $this->tomorrowAt(),
        ]);
    }

    #[Test]
    public function the_group_must_have_one_to_six_trainees(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $booker = $this->makeMember($gym);
        $six = array_map(fn () => $this->makeMember($gym), range(1, 6));

        $this->expectExceptionObject(ReservationException::groupSize(6));

        $this->bookAsTrainee($gym, $booker, $six, $trainer);
    }

    #[Test]
    public function six_trainees_is_allowed(): void
    {
        $gym = $this->makeGym();
        $others = array_map(fn () => $this->makeMember($gym), range(1, 5));

        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), $others, $this->makeAvailableTrainer($gym));

        $this->assertCount(6, $r->participants);
    }

    #[Test]
    public function the_payer_must_be_one_of_the_trainees(): void
    {
        $gym = $this->makeGym();
        $booker = $this->makeMember($gym);
        $outsider = $this->makeMember($gym);

        $this->expectExceptionObject(ReservationException::payerNotInGroup());

        $this->reservations()->create($gym, $this->tomorrowAt(), 1, [$booker->id], $outsider->id,
            $this->makeAvailableTrainer($gym), $booker->user, Reservation::VIA_TRAINEE);
    }

    #[Test]
    public function a_trainee_booking_must_include_the_booker(): void
    {
        $gym = $this->makeGym();
        $booker = $this->makeMember($gym);
        $friend = $this->makeMember($gym);

        $this->expectExceptionObject(ReservationException::bookerNotInGroup());

        $this->reservations()->create($gym, $this->tomorrowAt(), 1, [$friend->id], $friend->id,
            $this->makeAvailableTrainer($gym), $booker->user, Reservation::VIA_TRAINEE);
    }

    #[Test]
    public function a_trainer_booking_is_always_assigned_to_that_trainer(): void
    {
        $gym = $this->makeGym();
        $me = $this->makeAvailableTrainer($gym);
        $someoneElse = $this->makeAvailableTrainer($gym);
        $trainee = $this->makeMember($gym);

        $r = $this->reservations()->create($gym, $this->tomorrowAt(), 1, [$trainee->id], $trainee->id,
            $me, $me->user, Reservation::VIA_TRAINER);
        $this->assertSame($me->id, $r->trainer_id);

        $this->expectExceptionObject(ReservationException::notAllowed());

        $this->reservations()->create($gym, $this->tomorrowAt(20), 1, [$trainee->id], $trainee->id,
            $someoneElse, $me->user, Reservation::VIA_TRAINER);
    }

    #[Test]
    public function unapproved_members_cannot_be_added(): void
    {
        $gym = $this->makeGym();
        $pending = $this->makeMember($gym, null, ['status' => MemberStatus::Pending]);

        $this->expectException(ReservationException::class);

        $this->bookAsTrainee($gym, $this->makeMember($gym), [$pending], $this->makeAvailableTrainer($gym));
    }

    // --- Trainer ---

    #[Test]
    public function a_trainer_is_required_by_default(): void
    {
        $gym = $this->makeGym();

        $this->expectExceptionObject(ReservationException::trainerRequired());

        $this->bookAsTrainee($gym, $this->makeMember($gym));
    }

    #[Test]
    public function a_group_where_everyone_has_the_privilege_may_book_without_a_trainer(): void
    {
        $gym = $this->makeGym();
        $a = $this->makeMember($gym, null, ['can_book_without_trainer' => true]);
        $b = $this->makeMember($gym, null, ['can_book_without_trainer' => true]);

        $r = $this->bookAsTrainee($gym, $a, [$b]);

        $this->assertNull($r->trainer_id);
        $this->assertSame(2, SlotLock::where('resource', SlotLock::MEMBER)->count());
    }

    #[Test]
    public function by_default_one_trainee_without_the_privilege_means_a_trainer_is_needed(): void
    {
        // ข้อ 13 ยังไม่ยืนยัน ใช้ข้อเสนอในเอกสารเป็นค่าตั้งต้น: ทุกคนต้องมีสิทธิ์
        $gym = $this->makeGym();
        $privileged = $this->makeMember($gym, null, ['can_book_without_trainer' => true]);

        $this->expectExceptionObject(ReservationException::trainerRequired());

        $this->bookAsTrainee($gym, $privileged, [$this->makeMember($gym)]);
    }

    #[Test]
    public function the_no_trainer_rule_can_be_relaxed_once_policy_is_confirmed(): void
    {
        config(['gym.reservation.no_trainer_rule' => 'any']);

        $gym = $this->makeGym();
        $privileged = $this->makeMember($gym, null, ['can_book_without_trainer' => true]);

        $r = $this->bookAsTrainee($gym, $privileged, [$this->makeMember($gym)]);

        $this->assertNull($r->trainer_id);
    }

    #[Test]
    public function a_trainer_outside_their_available_hours_cannot_be_booked(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeTrainer($gym);
        $trainer->availabilities()->create(['day_of_week' => $this->tomorrowAt()->dayOfWeek, 'start_time' => '08:00', 'end_time' => '12:00']);

        $this->expectExceptionObject(ReservationException::trainerUnavailable());

        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, $this->tomorrowAt(18));
    }

    #[Test]
    public function a_trainer_must_be_free_for_every_hour_of_a_long_booking(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeTrainer($gym);
        $trainer->availabilities()->create(['day_of_week' => $this->tomorrowAt()->dayOfWeek, 'start_time' => '17:00', 'end_time' => '19:00']);

        $this->expectExceptionObject(ReservationException::trainerUnavailable());

        // ว่างแค่ถึง 19:00 แต่จองถึง 20:00
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, $this->tomorrowAt(18), 2);
    }

    #[Test]
    public function time_off_overrides_weekly_availability(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $trainer->timeOffs()->create(['date' => $this->tomorrowAt()->toDateString(), 'reason' => 'ลา']);

        $this->expectExceptionObject(ReservationException::trainerUnavailable());

        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer);
    }

    #[Test]
    public function a_trainer_not_accepting_bookings_cannot_be_chosen(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym, ['accepts_bookings' => false]);

        $this->expectExceptionObject(ReservationException::trainerUnavailable());

        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer);
    }

    // --- เวลา ---

    #[Test]
    public function bookings_open_up_to_forty_five_days_ahead(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);

        $ok = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::now()->addDays(45)->setTime(18, 0));
        $this->assertSame(ReservationStatus::PendingPayment, $ok->status);

        $this->expectExceptionObject(ReservationException::beyondWindow(45));

        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::now()->addDays(46)->setTime(18, 0));
    }

    #[Test]
    public function the_start_time_must_still_be_ahead(): void
    {
        $gym = $this->makeGym();

        $this->expectExceptionObject(ReservationException::inThePast());

        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), CarbonImmutable::now()->setTime(8, 0));
    }

    #[Test]
    public function every_hour_must_be_inside_opening_hours(): void
    {
        $gym = $this->makeGym();

        $this->expectExceptionObject(ReservationException::gymClosed());

        // ยิมปิด 22:00 จองช่วง 21:00–23:00 ไม่ได้
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), $this->tomorrowAt(21), 2);
    }

    #[Test]
    public function a_branch_without_a_price_cannot_take_bookings(): void
    {
        $gym = $this->makeGym(['hourly_rate' => 0]);

        try {
            $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
            $this->fail('ไม่ได้ตั้งราคาต้องจองไม่ได้ ไม่งั้นกลายเป็นจองฟรี');
        } catch (ReservationException $e) {
            $this->assertSame('price_not_set', $e->reason);
        }
    }

    #[Test]
    public function a_booking_close_to_its_start_must_be_paid_before_it_starts(): void
    {
        // ข้อ 13 ยังไม่ยืนยัน ใช้ข้อเสนอ: ครบ 30 นาทีหรือถึงเวลาเริ่ม แล้วแต่อะไรถึงก่อน
        $this->travelTo(CarbonImmutable::parse('2026-10-07 17:50'));
        $gym = $this->makeGym();

        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), CarbonImmutable::parse('2026-10-07 18:00'));

        $this->assertTrue($r->hold_expires_at->equalTo(CarbonImmutable::parse('2026-10-07 18:00')));
    }

    #[Test]
    public function a_trainer_of_another_branch_cannot_be_booked(): void
    {
        $gym = $this->makeGym();
        $elsewhere = $this->makeAvailableTrainer($this->makeGym());

        $this->expectExceptionObject(ReservationException::trainerUnavailable());

        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $elsewhere);
    }
}
