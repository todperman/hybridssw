<?php

namespace Tests\Feature;

use App\Enums\EarningStatus;
use App\Enums\TrainerType;
use App\Filament\Pages\PayoutSettings as SettingsPage;
use App\Models\TrainerEarning;
use App\Support\PayoutSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** หน้าตั้งค่าส่วนแบ่ง และนโยบายที่เปลี่ยนผลการคำนวณและสิ่งที่เทรนเนอร์เห็น */
class PayoutSettingsTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00'));
        Notification::fake();
    }

    #[Test]
    public function an_admin_saves_the_table_per_branch_and_the_policies_and_a_mismatch_is_refused(): void
    {
        $gym = $this->makeGym(['pricing' => config('gym.pricing'), 'payout' => null]);
        $this->actingAs($this->makeAdmin($gym));

        Livewire::test(SettingsPage::class)
            ->fillForm(['tiers' => [['trainer' => 800, 'facility' => 200]], 'extra_trainer' => 330, 'extra_facility' => 200])
            ->call('save')
            ->assertNotified('บันทึกไม่ได้ ตารางยังไม่ตรงกับราคา');

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'branch_id' => $gym->id,
                'tiers' => [['trainer' => 800, 'facility' => 250], ['trainer' => 1100, 'facility' => 450]],
                'extra_trainer' => 330, 'extra_facility' => 200,
                'internal' => 'table', 'discounts' => 'net', 'earn_when' => 'confirmed', 'cycle' => 'weekly', 'cycle_note' => 'โอนทุกวันศุกร์', 'visible' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('บันทึกแล้ว');

        $this->assertSame(800.0, $gym->fresh()->payoutTable()->trainerPerHour(1));
        $this->assertSame(['internal' => 'table', 'discounts' => 'net', 'earn_when' => 'confirmed', 'cycle' => 'weekly', 'cycle_note' => 'โอนทุกวันศุกร์', 'visible' => false], PayoutSettings::load());
        $this->assertTrue(\App\Models\AuditLog::where('action', 'payout.settings_changed')->exists());
    }

    #[Test]
    public function policies_change_who_earns_how_much_and_when(): void
    {
        $gym = $this->makeGym(['pricing' => config('gym.pricing'), 'payout' => null]);
        $admin = $this->makeAdmin($gym);
        PayoutSettings::save(['internal' => 'table', 'discounts' => 'net', 'earn_when' => 'confirmed', 'cycle' => 'monthly', 'visible' => true]);

        $internal = $this->makeAvailableTrainer($gym, ['type' => TrainerType::Internal]);
        $staff = $this->makeMember($gym);
        $staff->setMemberType(\App\Models\MemberType::where('code', 'hospital_staff')->firstOrFail(), null, $admin, 'ทดสอบ');

        // 1 คน (บุคลากร ฟรีทั้งยอด) → net = 0 แต่ internal ได้ตามตาราง และนับทันที
        $r = $this->bookAsTrainee($gym, $staff, [], $internal, CarbonImmutable::parse('2026-11-10 18:00'));
        $e = TrainerEarning::where('reservation_id', $r->id)->sole();
        $this->assertSame('0.00', $e->amount, 'ลูกค้าจ่าย 0 → สัดส่วน 0');
        $this->assertSame(EarningStatus::Payable, $e->status, 'นับทันทีที่ยืนยัน');

        // 2 คน คนหนึ่งฟรี → ลูกค้าจ่ายครึ่ง → ส่วนแบ่งครึ่งของ 1,000
        $friend = $this->makeMember($gym);
        $r2 = $this->confirmManually($this->bookAsTrainee($gym, $friend, [$staff], $internal, CarbonImmutable::parse('2026-11-11 18:00')));
        $this->assertSame('500.00', TrainerEarning::where('reservation_id', $r2->id)->sole()->amount);

        $this->actingAs($internal->user)->get('/trainer/jobs')->assertOk()->assertSee('trainer/earnings');
    }

    #[Test]
    public function trainers_see_the_share_table_and_policy_unless_hidden(): void
    {
        $gym = $this->makeGym(['pricing' => config('gym.pricing'), 'payout' => null, 'max_trainees' => 4]);
        $trainer = $this->makeAvailableTrainer($gym, ['type' => TrainerType::External]);

        $this->actingAs($trainer->user)->get('/trainer/earnings')
            ->assertOk()
            ->assertSee('ตารางส่วนแบ่งของคุณ')
            ->assertSee('4 คน')->assertDontSee('5 คน')
            ->assertSee('฿1,400')
            ->assertSee('นับเป็นรายได้เมื่อหมดเวลาใช้งานจริง')
            ->assertSee('โอนภายในวันที่ 5');

        PayoutSettings::save(['visible' => false]);
        $this->actingAs($trainer->user)->get('/trainer/earnings')->assertOk()->assertDontSee('ตารางส่วนแบ่งของคุณ');
    }
}
