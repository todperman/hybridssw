<?php

namespace Tests\Feature;

use App\Enums\EarningStatus;
use App\Enums\TrainerType;
use App\Filament\Pages\TrainerEarnings as AdminPage;
use App\Livewire\Trainer\Earnings as TrainerPage;
use App\Models\MemberType;
use App\Models\TrainerEarning;
use App\Notifications\AccountNotice;
use App\Support\PayoutTable;
use App\Support\PriceTable;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ส่วนแบ่งเทรนเนอร์: ตาราง การสร้าง/ยกเลิก/ย้าย การจ่ายรอบ หน้าแอดมิน และหน้าเทรนเนอร์ */
class TrainerEarningsTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00'));
        Notification::fake();
    }

    /** สาขาใช้ตารางราคาและส่วนแบ่งตั้งต้นของเจ้าของระบบ (ไม่ใช่ราคาเหมาของ makeGym) */
    protected function gym(): \App\Models\Branch
    {
        return $this->makeGym(['pricing' => config('gym.pricing'), 'payout' => null]);
    }

    protected function external(\App\Models\Branch $gym): \App\Models\Trainer
    {
        return $this->makeAvailableTrainer($gym, ['type' => TrainerType::External]);
    }

    #[Test]
    public function the_payout_table_splits_by_group_size_and_matches_the_price_table(): void
    {
        $table = PayoutTable::fromArray(config('gym.payout'));

        $this->assertSame(['trainer' => 700.0, 'facility' => 350.0], $table->split(1));
        $this->assertSame(['trainer' => 1200.0, 'facility' => 750.0], $table->split(3));
        $this->assertSame(['trainer' => 1500.0, 'facility' => 1150.0], $table->split(5));
        $this->assertSame(['trainer' => 2160.0, 'facility' => 1550.0], $table->split(7), 'เกิน 5 คน: +330/+200 ต่อคน');
        $this->assertSame([], $table->mismatches(PriceTable::fromArray(config('gym.pricing'))), 'Trainer + Facility ต้องเท่าราคาทุกแถว');

        $broken = PayoutTable::fromArray(['tiers' => [['trainer' => 700, 'facility' => 300]], 'extra' => ['trainer' => 330, 'facility' => 200]]);
        $this->assertCount(1, $broken->mismatches(PriceTable::fromArray(config('gym.pricing'))));
    }

    #[Test]
    public function confirming_a_booking_records_the_share_from_group_size_not_from_what_the_customer_paid(): void
    {
        $gym = $this->gym();
        $trainer = $this->external($gym);
        $staff = $this->makeMember($gym);
        $staff->setMemberType(MemberType::where('code', 'hospital_staff')->firstOrFail(), null, $this->makeAdmin($gym), 'ทดสอบ');
        $friends = [$this->makeMember($gym), $this->makeMember($gym)];

        $reservation = $this->confirmManually($this->bookAsTrainee($gym, $friends[0], [$staff, $friends[1]], $trainer, CarbonImmutable::parse('2026-11-05 18:00'), hours: 2));

        $e = TrainerEarning::where('reservation_id', $reservation->id)->sole();
        $this->assertSame(3, $e->people);
        $this->assertSame(2, $e->hours);
        $this->assertSame('1200.00', $e->per_hour);
        $this->assertSame('2400.00', $e->amount, 'ส่วนของคนฟรีไม่ลดส่วนแบ่ง');
        $this->assertSame('1500.00', $e->facility_amount);
        $this->assertLessThan(3900, (float) $e->customer_amount, 'ลูกค้าจ่ายน้อยกว่าราคาเต็มเพราะมีคนฟรี');
        $this->assertSame(EarningStatus::Accrued, $e->status);
        $this->assertTrue($e->earned_at->equalTo($reservation->ends_at));
    }

    #[Test]
    public function internal_trainers_and_bookings_without_a_trainer_earn_nothing(): void
    {
        $gym = $this->gym();
        $internal = $this->makeAvailableTrainer($gym, ['type' => TrainerType::Internal]);
        $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $internal, CarbonImmutable::parse('2026-11-05 18:00')));

        $solo = $this->makeMember($gym);
        $solo->setNoTrainerPrivilege(true, $this->makeAdmin($gym), 'ทดสอบ');
        $this->confirmManually($this->bookAsTrainee($gym, $solo, [], null, CarbonImmutable::parse('2026-11-06 18:00')));

        $this->assertSame(0, TrainerEarning::count());
    }

    #[Test]
    public function cancelling_voids_rescheduling_moves_the_date_and_changing_trainer_moves_the_share(): void
    {
        $gym = $this->gym();
        $t1 = $this->external($gym);
        $t2 = $this->external($gym);
        $admin = $this->makeAdmin($gym);
        $member = $this->makeMember($gym);

        $r = $this->confirmManually($this->bookAsTrainee($gym, $member, [], $t1, CarbonImmutable::parse('2026-11-10 18:00')));
        $this->reservations()->reschedule($r->fresh(), CarbonImmutable::parse('2026-11-12 10:00'), $admin, 'ทดสอบ', approved: true);
        $e = TrainerEarning::where('reservation_id', $r->id)->sole();
        $this->assertSame('2026-11-12 11:00', $e->earned_at->format('Y-m-d H:i'));

        $this->reservations()->changeTrainer($r->fresh(), $t2, $admin, 'ครูป่วย');
        $this->assertSame($t2->id, $e->fresh()->trainer_id);
        $this->assertSame(1, TrainerEarning::count(), 'ยังเป็นแถวเดียวต่อการจอง');

        $this->reservations()->cancelByAdmin($r->fresh(), $admin, 'ยิมปิด');
        $this->assertSame(EarningStatus::Void, $e->fresh()->status);
        $this->assertSame('การจองถูกยกเลิก', $e->fresh()->void_reason);

        // จองรอชำระแล้วหมดอายุ ไม่เคยมีส่วนแบ่ง
        $pending = $this->bookAsTrainee($gym, $member, [], $t1, CarbonImmutable::parse('2026-11-15 18:00'));
        $this->travel(31)->minutes();
        $this->reservations()->expire($pending->id);
        $this->assertNull(TrainerEarning::where('reservation_id', $pending->id)->first());
    }

    #[Test]
    public function shares_become_payable_after_the_session_and_a_monthly_payout_marks_them_paid_and_tells_the_trainer(): void
    {
        $gym = $this->gym();
        $trainer = $this->external($gym);
        $admin = $this->makeAdmin($gym);
        $member = $this->makeMember($gym);

        $past = $this->confirmManually($this->bookAsTrainee($gym, $member, [], $trainer, CarbonImmutable::parse('2026-11-04 18:00')));
        $future = $this->confirmManually($this->bookAsTrainee($gym, $member, [], $trainer, CarbonImmutable::parse('2026-11-20 18:00')));

        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00'));
        $this->artisan('earnings:settle')->assertSuccessful();

        $this->assertSame(EarningStatus::Payable, TrainerEarning::where('reservation_id', $past->id)->sole()->status);
        $this->assertSame(EarningStatus::Accrued, TrainerEarning::where('reservation_id', $future->id)->sole()->status);

        $result = app(\App\Services\TrainerEarningService::class)->pay($trainer, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')->endOfDay(), $admin, 'โอน 5/11');
        $this->assertSame(['count' => 1, 'amount' => 700.0], $result);

        $paid = TrainerEarning::where('reservation_id', $past->id)->sole();
        $this->assertSame(EarningStatus::Paid, $paid->status);
        $this->assertSame($admin->id, $paid->paid_by_user_id);
        $this->assertSame('โอน 5/11', $paid->payout_note);
        Notification::assertSentTo($trainer->user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->title, 'จ่ายส่วนแบ่งแล้ว') && str_contains($n->title, '700'));
        $this->assertTrue(\App\Models\AuditLog::where('action', 'earning.paid')->exists());

        // ยกเลิกหลังจ่ายแล้ว: ยอดคงไว้ แต่ลงประวัติให้แอดมินตามหัก
        $this->reservations()->cancelByAdmin($past->fresh(), $admin, 'ลูกค้าร้องเรียน');
        $this->assertSame(EarningStatus::Paid, $paid->fresh()->status);
        $this->assertTrue(\App\Models\AuditLog::where('action', 'earning.paid_but_cancelled')->exists());
    }

    #[Test]
    public function the_admin_page_summarises_by_trainer_or_by_day_pays_a_round_and_exports_csv(): void
    {
        $gym = $this->gym();
        $trainer = $this->external($gym);
        $admin = $this->makeAdmin($gym);
        $member = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $member, [$this->makeMember($gym)], $trainer, CarbonImmutable::parse('2026-11-04 18:00'), hours: 2));
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00'));

        $this->actingAs($admin)->get('/admin/trainer-earnings?m=2026-11')
            ->assertOk()
            ->assertSee('ส่วนแบ่งเทรนเนอร์')
            ->assertSee($trainer->user->name)
            ->assertSee($r->reference)
            ->assertSee('2,000')
            ->assertSee('รอจ่าย');

        Livewire::actingAs($admin)->test(AdminPage::class, ['m' => '2026-11'])
            ->set('mode', 'day')
            ->assertSee('รวมเดือนนี้')
            ->set('mode', 'trainer')
            ->callAction('pay', ['note' => 'โอนแล้ว'], ['trainer' => $trainer->id])
            ->assertNotified();

        $this->assertSame(EarningStatus::Paid, TrainerEarning::where('reservation_id', $r->id)->sole()->status);

        $csv = Livewire::actingAs($admin)->test(AdminPage::class, ['m' => '2026-11'])->call('export');
        $csv->assertFileDownloaded('trainer-earnings-2026-11.csv');
    }

    #[Test]
    public function trainers_see_only_their_own_earnings_and_internal_trainers_see_the_notice(): void
    {
        $gym = $this->gym();
        $mine = $this->external($gym);
        $other = $this->external($gym);
        $member = $this->makeMember($gym);
        $r1 = $this->confirmManually($this->bookAsTrainee($gym, $member, [], $mine, CarbonImmutable::parse('2026-11-04 18:00')));
        $r2 = $this->confirmManually($this->bookAsTrainee($gym, $member, [], $other, CarbonImmutable::parse('2026-11-04 19:00')));

        $this->actingAs($mine->user)->get('/trainer/earnings?m=2026-11')
            ->assertOk()
            ->assertSee('ส่วนแบ่งของคุณ')
            ->assertSee($r1->reference, false)
            ->assertDontSee($r2->reference)
            ->assertSee('700');

        $this->actingAs($mine->user)->get('/trainer/jobs')->assertOk()->assertSee('รายได้');

        $internal = $this->makeAvailableTrainer($gym, ['type' => TrainerType::Internal]);
        $this->actingAs($internal->user)->get('/trainer/earnings')->assertOk()->assertSee('ไม่มีส่วนแบ่ง');
        $this->actingAs($internal->user)->get('/trainer/jobs')->assertOk()->assertDontSee('trainer/earnings');

        $this->actingAs($member->user)->get('/trainer/earnings')->assertForbidden();
    }

    #[Test]
    public function the_backfill_command_creates_shares_for_bookings_confirmed_before_the_feature(): void
    {
        $gym = $this->gym();
        $trainer = $this->external($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::parse('2026-11-04 18:00')));
        TrainerEarning::query()->delete();

        $this->artisan('earnings:backfill')->assertSuccessful();

        $this->assertSame('700.00', TrainerEarning::where('reservation_id', $r->id)->sole()->amount);
    }
}
