<?php

namespace Tests\Feature\Reservations;

use App\Enums\RefundStatus;
use App\Enums\RequestStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SlotLock;
use App\Services\Reservations\MemberLookup;
use App\Services\Reservations\TrainerScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ข้อ 3 ข้อ 8 ข้อ 9 และข้อ 10 */
class ChangeReservationTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));
        Notification::fake();
    }

    // --- ข้อ 8 เลื่อนวันเวลา ---

    #[Test]
    public function the_payer_can_move_a_booking_before_midnight_of_the_day_of_use(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $payer, [], $this->makeAvailableTrainer($gym)));
        $oldStart = $r->starts_at->copy();

        $moved = $this->reservations()->reschedule($r, $this->tomorrowAt(20), $payer->user, 'ติดธุระ');

        $this->assertTrue($moved->starts_at->equalTo($this->tomorrowAt(20)));
        $this->assertSame('800.00', (string) $moved->amount, 'คงราคาและยอดชำระเดิม');
        $this->assertSame(0, SlotLock::where('slot_start', $oldStart)->count(), 'คืนเวลาเดิม');
        $this->assertSame(3, SlotLock::where('slot_start', $this->tomorrowAt(20))->count(), 'ล็อกเวลาใหม่');

        $log = AuditLog::where('action', 'reservation.rescheduled')->firstOrFail();
        $this->assertSame($payer->user->id, $log->actor_user_id);
        $this->assertSame('ติดธุระ', $log->reason);
        $this->assertSame($oldStart->toDateTimeString(), $log->before['starts_at']);
    }

    #[Test]
    public function a_one_hour_shift_into_its_own_slot_works(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $payer, [], $this->makeAvailableTrainer($gym), $this->tomorrowAt(18), 2));

        // 18–20 เลื่อนเป็น 19–21 ทับชั่วโมงของตัวเองตรง 19:00
        $moved = $this->reservations()->reschedule($r, $this->tomorrowAt(19), $payer->user);

        $this->assertTrue($moved->starts_at->equalTo($this->tomorrowAt(19)));
    }

    #[Test]
    public function on_the_day_of_use_the_trainee_must_ask_the_admin(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $payer, [], $this->makeAvailableTrainer($gym)));

        // จองวันที่ 8 เวลา 18:00 เส้นตายคือ 7 เวลา 23:59
        $this->travelTo(CarbonImmutable::parse('2026-10-08 00:00'));

        $this->expectExceptionObject(ReservationException::needsApproval());

        $this->reservations()->reschedule($r, $this->tomorrowAt(20), $payer->user);
    }

    #[Test]
    public function an_approved_request_moves_the_booking_and_rechecks_availability(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $trainer = $this->makeAvailableTrainer($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $payer, [], $trainer, CarbonImmutable::parse('2026-10-08 18:00')));

        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00'));

        $request = $this->reservations()->requestReschedule($r, CarbonImmutable::parse('2026-10-09 18:00'), $payer->user, 'ป่วย');
        $this->assertSame(RequestStatus::Pending, $request->status);
        $this->assertTrue($r->fresh()->starts_at->equalTo(CarbonImmutable::parse('2026-10-08 18:00')), 'ระหว่างรอ การจองเดิมยังอยู่');

        $this->reservations()->approveRequest($request, $this->makeAdmin($gym));

        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertTrue($r->fresh()->starts_at->equalTo(CarbonImmutable::parse('2026-10-09 18:00')));
    }

    #[Test]
    public function approval_is_refused_when_the_new_time_was_taken_meanwhile(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $payer, [], $this->makeAvailableTrainer($gym), CarbonImmutable::parse('2026-10-08 18:00')));

        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00'));
        $request = $this->reservations()->requestReschedule($r, CarbonImmutable::parse('2026-10-09 18:00'), $payer->user, 'ป่วย');

        // ระหว่างรอแอดมิน มีคนจองเวลาใหม่นั้นไปแล้ว
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym), CarbonImmutable::parse('2026-10-09 18:00'));

        try {
            $this->reservations()->approveRequest($request, $this->makeAdmin($gym));
            $this->fail('ต้องตรวจความว่างอีกครั้งก่อนอนุมัติ');
        } catch (ReservationException $e) {
            $this->assertSame('gym_taken', $e->reason);
        }

        $this->assertSame(RequestStatus::Pending, $request->fresh()->status);
        $this->assertTrue($r->fresh()->starts_at->equalTo(CarbonImmutable::parse('2026-10-08 18:00')));
    }

    #[Test]
    public function someone_outside_the_booking_cannot_move_it(): void
    {
        $gym = $this->makeGym();
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym)));

        $this->expectExceptionObject(ReservationException::notAllowed());

        $this->reservations()->reschedule($r, $this->tomorrowAt(20), $this->makeMember($gym)->user);
    }

    #[Test]
    public function an_unpaid_booking_cannot_be_moved(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $r = $this->bookAsTrainee($gym, $payer, [], $this->makeAvailableTrainer($gym));

        $this->expectExceptionObject(ReservationException::notConfirmed());

        $this->reservations()->reschedule($r, $this->tomorrowAt(20), $payer->user);
    }

    #[Test]
    public function the_trainer_follows_the_same_deadline(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer));

        $moved = $this->reservations()->reschedule($r, $this->tomorrowAt(20), $trainer->user, 'ตกลงกับลูกเทรนแล้ว');
        $this->assertTrue($moved->starts_at->equalTo($this->tomorrowAt(20)));

        $this->travelTo($this->tomorrowAt(8));
        $this->expectExceptionObject(ReservationException::needsApproval());
        $this->reservations()->reschedule($moved, $this->tomorrowAt(21), $trainer->user);
    }

    // --- ข้อ 9 Trainer ขอยกเลิกการรับงาน ---

    #[Test]
    public function a_withdrawal_is_resolved_by_assigning_an_available_replacement(): void
    {
        $gym = $this->makeGym();
        $original = $this->makeAvailableTrainer($gym);
        $replacement = $this->makeAvailableTrainer($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $original));

        $request = $this->reservations()->requestTrainerWithdrawal($r, $original->user, 'บาดเจ็บ');
        $this->assertSame($original->id, $r->fresh()->trainer_id, 'ระหว่างดำเนินการ การจองยังคงอยู่');

        $this->reservations()->approveRequest($request, $this->makeAdmin($gym), $replacement);

        $r->refresh();
        $this->assertSame($replacement->id, $r->trainer_id);
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame(1, SlotLock::where('resource', SlotLock::TRAINER)->where('resource_id', $replacement->id)->count());
        $this->assertSame(0, SlotLock::where('resource', SlotLock::TRAINER)->where('resource_id', $original->id)->count());
        $this->assertTrue(AuditLog::where('action', 'reservation.trainer_changed')->exists());
    }

    #[Test]
    public function a_replacement_must_be_free_for_the_whole_booking(): void
    {
        $gym = $this->makeGym();
        $original = $this->makeAvailableTrainer($gym);
        $busy = $this->makeTrainer($gym); // ไม่ได้ตั้งเวลาว่างไว้เลย
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $original));

        $request = $this->reservations()->requestTrainerWithdrawal($r, $original->user, 'บาดเจ็บ');

        $this->expectExceptionObject(ReservationException::trainerUnavailable());

        $this->reservations()->approveRequest($request, $this->makeAdmin($gym), $busy);
    }

    #[Test]
    public function only_the_assigned_trainer_can_ask_to_withdraw(): void
    {
        $gym = $this->makeGym();
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym)));

        $this->expectExceptionObject(ReservationException::notAllowed());

        $this->reservations()->requestTrainerWithdrawal($r, $this->makeAvailableTrainer($gym)->user, 'ไม่ว่าง');
    }

    // --- ข้อ 10 ยกเลิกและคืนเงิน ---

    #[Test]
    public function an_admin_cancellation_refunds_the_original_payer_in_full(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $admin = $this->makeAdmin($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $payer, [$this->makeMember($gym)], $this->makeAvailableTrainer($gym)), $admin);

        $r = $this->reservations()->cancelByAdmin($r, $admin, 'หา Trainer ทดแทนไม่ได้');

        $this->assertSame(ReservationStatus::Cancelled, $r->status);
        $this->assertSame(RefundStatus::Pending, $r->refund_status, 'ช่องทาง manual รอแอดมินโอนคืน');
        $this->assertSame(0, SlotLock::where('reservation_id', $r->id)->count(), 'คืนพื้นที่และเวลาทุกคน');

        $refund = $r->refunds()->firstOrFail();
        $this->assertSame('800.00', (string) $refund->amount);
        $this->assertSame($payer->id, $refund->payment->payer_member_id);
        $this->assertSame($admin->id, $refund->approved_by_user_id);

        $this->payments()->settleRefund($refund, RefundStatus::Succeeded, $admin, 'โอนคืนแล้ว');

        $this->assertSame(RefundStatus::Succeeded, $r->fresh()->refund_status);
        $this->assertTrue(AuditLog::where('action', 'refund.updated')->exists());
    }

    #[Test]
    public function an_omise_payment_is_refunded_through_omise(): void
    {
        config(['services.omise.secret_key' => 'skey_test_x']);

        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
        $this->payments()->recordSuccess($r, Payment::PROVIDER_OMISE, $r->amount, 'chrg_9');

        Http::fake(['api.omise.co/charges/chrg_9/refunds' => Http::response(['object' => 'refund', 'id' => 'rfnd_1'])]);

        $r = $this->reservations()->cancelByAdmin($r->fresh(), $admin, 'ยิมปิดฉุกเฉิน');

        $this->assertSame(RefundStatus::Succeeded, $r->refund_status);
        $this->assertSame('rfnd_1', $r->refunds()->first()->provider_refund_id);
    }

    #[Test]
    public function a_refund_omise_rejects_is_marked_failed_for_the_admin_to_handle(): void
    {
        config(['services.omise.secret_key' => 'skey_test_x']);

        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
        $this->payments()->recordSuccess($r, Payment::PROVIDER_OMISE, $r->amount, 'chrg_10');

        Http::fake(['api.omise.co/charges/chrg_10/refunds' => Http::response(['message' => 'refund not supported'], 400)]);

        $r = $this->reservations()->cancelByAdmin($r->fresh(), $admin, 'ยิมปิดฉุกเฉิน');

        $this->assertSame(RefundStatus::Failed, $r->refund_status);
        $this->assertSame('refund not supported', $r->refunds()->first()->failure_message);
    }

    // --- ข้อ 3 Trainer ลบเวลาที่มีงานไม่ได้ ---

    #[Test]
    public function a_trainer_cannot_remove_hours_that_have_a_booking(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeTrainer($gym);
        $window = $trainer->availabilities()->create(['day_of_week' => $this->tomorrowAt()->dayOfWeek, 'start_time' => '06:00', 'end_time' => '22:00']);
        $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer));

        $this->expectExceptionObject(ReservationException::lockedByReservation());

        app(TrainerScheduleService::class)->removeWindow($window);
    }

    #[Test]
    public function a_trainer_cannot_take_leave_over_a_booking(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer));

        try {
            app(TrainerScheduleService::class)->addTimeOff($trainer, $this->tomorrowAt()->toDateString(), '17:00', '19:00');
            $this->fail('ลาทับงานที่มีคนจองไม่ได้');
        } catch (ReservationException $e) {
            $this->assertSame('locked_by_reservation', $e->reason);
        }

        $this->assertSame(0, $trainer->timeOffs()->count(), 'ต้องย้อนกลับ ไม่บันทึกวันลาค้างไว้');

        // ลาช่วงที่ไม่มีงานได้ตามปกติ
        app(TrainerScheduleService::class)->addTimeOff($trainer, $this->tomorrowAt()->toDateString(), '08:00', '10:00');
        $this->assertSame(1, $trainer->timeOffs()->count());
    }

    // --- ข้อ 5 หาเพื่อนร่วมกลุ่ม ---

    #[Test]
    public function friends_are_found_only_by_exact_member_code_or_phone(): void
    {
        $gym = $this->makeGym();
        $friend = $this->makeMember($gym);
        $friend->user->update(['phone' => '0812345678']);
        $lookup = app(MemberLookup::class);

        $this->assertSame($friend->id, $lookup->find($gym, $friend->member_code)?->id);
        $this->assertSame($friend->id, $lookup->find($gym, strtolower($friend->member_code))?->id);
        $this->assertSame($friend->id, $lookup->find($gym, '081-234-5678')?->id);

        $this->assertNull($lookup->find($gym, '081234'), 'ค้นบางส่วนไม่ได้');
        $this->assertNull($lookup->find($gym, substr($friend->member_code, 0, 4)));
        $this->assertNull($lookup->find($this->makeGym(), $friend->member_code), 'คนละสาขาหาไม่เจอ');
    }
}
