<?php

namespace Tests\Feature\Reservations;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Livewire\MemberRegistration;
use App\Livewire\Reservations\BookingWizard;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** สิทธิ์ใช้ฟรีของบุคลากร รพ. และแพทย์: ฟรีเฉพาะส่วนของตัวเอง รวมค่า Trainer ตามโควตาต่อเดือน */
class MemberBenefitTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00'));
        Notification::fake();
    }

    protected function staffType(?int $quota = null): MemberType
    {
        return tap(MemberType::where('code', 'hospital_staff')->sole())->update(['free_hours_per_month' => $quota]);
    }

    protected function staffMember($gym, ?int $quota = null, ?string $expires = null): Member
    {
        $member = $this->makeMember($gym);
        $member->setMemberType($this->staffType($quota), $expires ? CarbonImmutable::parse($expires) : null, $this->makeAdmin($gym), 'ทดสอบ');

        return $member->fresh();
    }

    #[Test]
    public function the_default_types_exist_after_migrating(): void
    {
        $this->assertSame(['hospital_staff', 'doctor'], MemberType::orderBy('sort_order')->pluck('code')->all());
        $this->assertTrue(MemberType::first()->isUnlimited());
    }

    #[Test]
    public function a_staff_member_alone_books_for_free_and_is_confirmed_without_paying(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $trainer = $this->makeAvailableTrainer($gym);
        $staff = $this->staffMember($gym);

        $r = $this->bookAsTrainee($gym, $staff, [], $trainer, hours: 2);

        $this->assertSame('0.00', (string) $r->amount);
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertNull($r->hold_expires_at);
        $this->assertNotNull($r->confirmed_at);
        $this->assertSame(0, $r->payments()->count());
        $this->assertSame(2, (int) $r->participants()->first()->pivot->free_hours);
        $this->assertTrue(AuditLog::where('action', 'reservation.waived')->exists());
        $this->assertSame('1 คน × ฿1,050/คน/ชม. × 2 ชม. (มี Trainer) · ฟรี 1 คน', $r->priceLabel());
    }

    #[Test]
    public function with_friends_only_the_staff_share_is_waived(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $trainer = $this->makeAvailableTrainer($gym);
        $staff = $this->staffMember($gym);
        $friend = $this->makeMember($gym);
        $friend2 = $this->makeMember($gym);

        // 3 คน มี Trainer = 1,950/ชม. ส่วนละ 650 บุคลากร 1 คนฟรี เพื่อน 2 คนจ่าย 1,300
        $r = $this->bookAsTrainee($gym, $friend, [$staff, $friend2], $trainer);

        $this->assertSame('1300.00', (string) $r->amount);
        $this->assertSame(ReservationStatus::PendingPayment, $r->status);
        $this->assertSame([$staff->id => 1], $r->pricing['free']);
    }

    #[Test]
    public function the_monthly_quota_caps_free_hours_and_the_rest_is_charged(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $trainer = $this->makeAvailableTrainer($gym);
        $staff = $this->staffMember($gym, quota: 3);

        $this->bookAsTrainee($gym, $staff, [], $trainer, CarbonImmutable::parse('2026-10-16 10:00'), hours: 2);
        $this->assertSame(1, $staff->freeHoursLeftIn(CarbonImmutable::parse('2026-10-20')));

        // เหลือโควตา 1 ชม. จอง 2 ชม. → ฟรี 1 จ่าย 1 (1,050)
        $second = $this->bookAsTrainee($gym, $staff, [], $trainer, CarbonImmutable::parse('2026-10-17 10:00'), hours: 2);
        $this->assertSame('1050.00', (string) $second->amount);
        $this->assertSame(ReservationStatus::PendingPayment, $second->status);

        // เดือนถัดไปโควตาเริ่มใหม่
        $this->assertSame(3, $staff->freeHoursLeftIn(CarbonImmutable::parse('2026-11-01')));
    }

    #[Test]
    public function expired_or_disabled_benefits_charge_the_normal_price(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $trainer = $this->makeAvailableTrainer($gym);

        $expired = $this->staffMember($gym, expires: '2026-10-14');
        $this->assertSame('1050.00', (string) $this->bookAsTrainee($gym, $expired, [], $trainer)->amount);

        $disabled = $this->staffMember($gym);
        MemberType::where('code', 'hospital_staff')->update(['is_active' => false]);
        $this->assertSame('1050.00', (string) $this->bookAsTrainee($gym, $disabled->fresh(), [], $trainer, CarbonImmutable::parse('2026-10-17 10:00'))->amount);
    }

    #[Test]
    public function the_booking_page_shows_who_is_free_and_changes_the_button(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $this->makeAvailableTrainer($gym);
        $staff = $this->staffMember($gym);

        Livewire::actingAs($staff->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-16')
            ->assertSee('ฟรี')
            ->assertSee('ยืนยันการจอง (ฟรี)')
            ->assertDontSee('จองและไปชำระเงิน');
    }

    #[Test]
    public function registration_records_the_staff_claim_for_the_admin_to_verify(): void
    {
        $this->makeGym();

        Livewire::test(MemberRegistration::class)
            ->set('first_name', 'หมอ')->set('last_name', 'ใจดี')
            ->set('phone', '0812345678')->set('email', 'doc@example.test')
            ->set('password', 'Hybrid#2026ssw')->set('password_confirmation', 'Hybrid#2026ssw')
            ->set('emergency_contact_name', 'สมหญิง')->set('emergency_contact_phone', '0898765432')
            ->set('accept', true)
            ->set('hospital_staff', true)
            ->call('register')
            ->assertHasErrors(['staff_reference'])
            ->set('staff_reference', 'ว.12345')
            ->call('register')
            ->assertHasNoErrors();

        $member = User::where('email', 'doc@example.test')->sole()->member;
        $this->assertSame('บุคลากร/แพทย์ รพ. · ว.12345', $member->benefit_claim);
        $this->assertNull($member->member_type_id, 'ต้องให้แอดมินตรวจก่อน ไม่ได้สิทธิ์อัตโนมัติ');
    }

    #[Test]
    public function the_admin_grants_a_type_with_expiry_and_reason_and_it_is_logged(): void
    {
        $gym = $this->makeGym();
        $member = $this->makeMember($gym);
        $admin = $this->makeAdmin($gym);
        $doctor = MemberType::where('code', 'doctor')->sole();

        $this->actingAs($admin);
        Livewire::test(ListMembers::class)
            ->set('activeTab', 'all')
            ->callTableAction('memberType', $member, data: ['member_type_id' => $doctor->id, 'benefit_expires_at' => '2027-03-31', 'reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        Livewire::test(ListMembers::class)
            ->set('activeTab', 'all')
            ->callTableAction('memberType', $member, data: ['member_type_id' => $doctor->id, 'benefit_expires_at' => '2027-03-31', 'reason' => 'ตรวจใบประกอบวิชาชีพแล้ว']);

        $member->refresh();
        $this->assertSame($doctor->id, $member->member_type_id);
        $this->assertSame('2027-03-31', $member->benefit_expires_at->toDateString());
        $this->assertTrue($member->hasBenefit());

        $log = AuditLog::where('action', 'member.type_changed')->sole();
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('แพทย์', $log->after['member_type']);

        Livewire::test(ListMembers::class)
            ->set('activeTab', 'all')
            ->callTableAction('memberType', $member, data: ['member_type_id' => '', 'reason' => 'ลาออกแล้ว']);

        $this->assertNull($member->fresh()->member_type_id);
        $this->assertSame(2, AuditLog::where('action', 'member.type_changed')->count());
    }
}
