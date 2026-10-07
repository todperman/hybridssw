<?php

namespace Tests\Feature\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RequestStatus;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\ReservationRequests\Pages\ListReservationRequests;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\AuditLog;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** หลังบ้านของระบบจอง Private Gym */
class AdminReservationsTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));
        Notification::fake();
    }

    #[Test]
    public function every_admin_page_opens(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->actingAs($this->makeAdmin($gym));

        foreach (['/admin/reservations', '/admin/reservations/'.$r->id, '/admin/reservation-requests', '/admin/payments', '/admin/audit-logs', '/admin/branches/'.$gym->id.'/edit', '/admin/members'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[Test]
    public function the_admin_records_a_transfer_and_the_booking_is_confirmed(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
        $admin = $this->makeAdmin($gym);

        $this->assertSame('1', ReservationResource::getNavigationBadge());

        $this->actingAs($admin);
        Livewire::test(ListReservations::class)
            ->set('activeTab', 'pending')
            ->assertCanSeeTableRecords([$r])
            ->callTableAction('recordPayment', $r, data: ['amount' => '800', 'note' => 'สลิป 123']);

        $r->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame($admin->id, $r->successfulPayment()->recorded_by_user_id);
    }

    #[Test]
    public function a_wrong_amount_is_refused_without_crashing_the_page(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->actingAs($this->makeAdmin($gym));
        Livewire::test(ListReservations::class)
            ->set('activeTab', 'pending')
            ->callTableAction('recordPayment', $r, data: ['amount' => '500']);

        $this->assertSame(ReservationStatus::PendingPayment, $r->refresh()->status);
        $this->assertSame(0, $r->payments()->count());
    }

    #[Test]
    public function the_admin_reschedules_after_the_deadline_from_the_detail_page(): void
    {
        $gym = $this->makeGym();
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym)));
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00'));

        $this->actingAs($this->makeAdmin($gym));
        Livewire::test(ViewReservation::class, ['record' => $r->id])
            ->callAction('reschedule', data: ['date' => '2026-10-09', 'time' => '07:00', 'reason' => 'ลูกค้าโทรขอ'])
            ->assertHasNoActionErrors();

        $this->assertSame('2026-10-09 07:00', $r->refresh()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('ลูกค้าโทรขอ', AuditLog::where('action', 'reservation.rescheduled')->sole()->reason);
    }

    #[Test]
    public function reschedule_options_skip_times_the_trainer_cannot_cover(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeTrainer($gym);
        $trainer->availabilities()->create(['day_of_week' => 4, 'start_time' => '17:00', 'end_time' => '19:00']);
        $trainer->availabilities()->create(['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '11:00']);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer));

        $options = \App\Filament\Resources\Reservations\ReservationActions::rescheduleOptions($r->load('participants', 'trainer', 'branch'), '2026-10-09');

        $this->assertSame(['09:00', '10:00'], array_keys($options));
    }

    #[Test]
    public function cancelling_a_paid_booking_frees_the_time_and_tracks_the_refund_until_it_is_settled(): void
    {
        $gym = $this->makeGym();
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym)));

        $this->actingAs($this->makeAdmin($gym));
        Livewire::test(ViewReservation::class, ['record' => $r->id])
            ->callAction('cancel', data: ['reason' => 'ยิมปิดซ่อม']);

        $r->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $r->status);
        $this->assertSame(RefundStatus::Pending, $r->refund_status);
        $this->assertSame(0, $r->locks()->count());
        $this->assertSame('1', ReservationResource::getNavigationBadge());

        Livewire::test(ViewReservation::class, ['record' => $r->id])
            ->callAction('settleRefund', data: ['status' => 'succeeded', 'message' => 'โอนคืนแล้ว']);

        $this->assertSame(RefundStatus::Succeeded, $r->refresh()->refund_status);
        $this->assertNull(ReservationResource::getNavigationBadge());
    }

    #[Test]
    public function approving_a_withdrawal_hands_the_job_to_the_chosen_trainer(): void
    {
        $gym = $this->makeGym();
        $old = $this->makeAvailableTrainer($gym);
        $new = $this->makeAvailableTrainer($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $old));
        $request = $this->reservations()->requestTrainerWithdrawal($r, $old->user, 'ป่วย');

        $this->actingAs($this->makeAdmin($gym));
        Livewire::test(ListReservationRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('approve', $request, data: ['trainer_id' => $new->id, 'note' => 'ได้คนแทนแล้ว']);

        $this->assertSame($new->id, $r->refresh()->trainer_id);
        $this->assertSame(RequestStatus::Approved, $request->refresh()->status);
    }

    #[Test]
    public function a_rejected_request_keeps_the_booking_as_it_was(): void
    {
        $gym = $this->makeGym();
        $member = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $member, [], $this->makeAvailableTrainer($gym)));
        $this->travelTo(CarbonImmutable::parse('2026-10-08 08:00'));
        $request = $this->reservations()->requestReschedule($r, CarbonImmutable::parse('2026-10-09 07:00'), $member->user, 'ติดงาน');

        $this->actingAs($this->makeAdmin($gym));
        Livewire::test(ListReservationRequests::class)
            ->callTableAction('reject', $request, data: ['note' => 'เลยเวลาแล้ว']);

        $this->assertSame(RequestStatus::Rejected, $request->refresh()->status);
        $this->assertSame('2026-10-08 18:00', $r->refresh()->starts_at->format('Y-m-d H:i'));
    }

    #[Test]
    public function a_late_payment_that_lost_its_slot_is_refunded_from_the_payments_page(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $late = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer);

        $this->travel(31)->minutes();
        $this->reservations()->expireStaleHolds();
        $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $payment = $this->payments()->recordSuccess($late, Payment::PROVIDER_OMISE, '800', 'chrg_test_1');
        $this->assertTrue($payment->needs_review);

        $this->actingAs($this->makeAdmin($gym));
        Livewire::test(ListPayments::class)
            ->assertSet('activeTab', 'review')
            ->assertCanSeeTableRecords([$payment])
            ->callTableAction('refund', $payment, data: ['reason' => 'เวลาถูกจองไปแล้ว']);

        $this->assertFalse($payment->refresh()->needs_review);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame(1, $late->refunds()->count());
    }

    #[Test]
    public function granting_the_no_trainer_privilege_needs_a_reason_and_is_logged(): void
    {
        $gym = $this->makeGym();
        $member = $this->makeMember($gym);
        $admin = $this->makeAdmin($gym);

        $this->actingAs($admin);
        Livewire::test(ListMembers::class)
            ->set('activeTab', 'all')
            ->callTableAction('noTrainerPrivilege', $member, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertFalse($member->refresh()->can_book_without_trainer);

        Livewire::test(ListMembers::class)
            ->set('activeTab', 'all')
            ->callTableAction('noTrainerPrivilege', $member, data: ['reason' => 'นักกีฬาทีมชาติ ฝึกเองได้']);

        $this->assertTrue($member->refresh()->can_book_without_trainer);
        $log = AuditLog::where('action', 'member.no_trainer_privilege')->sole();
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('นักกีฬาทีมชาติ ฝึกเองได้', $log->reason);
        $this->assertSame(['can_book_without_trainer' => true], $log->after);
    }

    #[Test]
    public function branch_staff_only_see_their_own_branch(): void
    {
        $gym = $this->makeGym();
        $other = $this->makeGym(['code' => 'OTHER']);
        $mine = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
        $theirs = $this->bookAsTrainee($other, $this->makeMember($other), [], $this->makeAvailableTrainer($other));

        $staff = $this->makeAdmin($gym);
        $staff->update(['role' => \App\Enums\UserRole::Staff]);

        $this->actingAs($staff->refresh());
        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }
}
