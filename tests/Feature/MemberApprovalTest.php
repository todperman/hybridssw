<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Livewire\MemberRegistration;
use App\Livewire\TeamJoin;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/**
 * คัดคนตั้งแต่ตอนสมัคร แทนการอนุมัติทีละการจอง
 * คนทั่วไปสมัครแล้วรออนุมัติ ผ่านแล้วจองได้ทันทีโดยไม่ต้องรออีก
 */
class MemberApprovalTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00'));
        Notification::fake();
    }

    /** สาขาที่ตั้งราคาและเวลาเปิดแล้ว พร้อม Trainer ว่าง สำหรับลองจองด้วยบัญชีที่เพิ่งสมัคร */
    protected function bookFor(User $user): \App\Models\Reservation
    {
        $branch = $user->member->branch;
        $branch->update(['hourly_rate' => 800]);

        foreach (range(0, 6) as $day) {
            $this->makeTemplate($branch, ['day_of_week' => $day, 'start_time' => '06:00', 'end_time' => '22:00']);
        }

        return $this->bookAsTrainee($branch, $user->member->fresh(), [], $this->makeAvailableTrainer($branch));
    }

    protected function register(): User
    {
        Branch::first() ?? Branch::create(['code' => 'B1', 'name' => 'สาขาทดสอบ']);

        Livewire::test(MemberRegistration::class)
            ->set('first_name', 'สมชาย')->set('last_name', 'ใจดี')
            ->set('phone', '0812345678')->set('email', 'somchai@example.test')
            ->set('password', 'Hybrid#2026ssw')->set('password_confirmation', 'Hybrid#2026ssw')
            ->set('emergency_contact_name', 'สมหญิง')->set('emergency_contact_phone', '0898765432')
            ->set('accept', true)
            ->call('register')
            ->assertHasNoErrors();

        return User::where('email', 'somchai@example.test')->firstOrFail();
    }

    protected function admin(): User
    {
        return User::create([
            'branch_id' => Branch::firstOrFail()->id, 'first_name' => 'แอด', 'last_name' => 'มิน',
            'email' => 'admin@example.test', 'password' => 'password', 'role' => UserRole::Admin,
        ])->refresh();
    }

    // --- การสมัครต้องอนุมัติ ---

    #[Test]
    public function a_public_sign_up_waits_for_approval(): void
    {
        $user = $this->register();

        $this->assertSame(MemberStatus::Pending, $user->member->status);
        $this->assertNull($user->member->approved_at);
    }

    #[Test]
    public function an_unapproved_member_only_sees_the_status_page(): void
    {
        $user = $this->register();

        $this->actingAs($user);

        $this->get(route('member.book'))->assertRedirect(route('member.pending'));
        $this->get(route('member.reservations'))->assertRedirect(route('member.pending'));
        $this->get(route('member.pending'))->assertOk()->assertSee('รอแอดมินอนุมัติ')->assertSee('สถานะการสมัคร');
    }

    #[Test]
    public function an_unapproved_member_is_refused_by_the_booking_service_too(): void
    {
        $user = $this->register();

        $this->expectException(ReservationException::class);

        $this->bookFor($user);
    }

    #[Test]
    public function the_admin_sees_new_sign_ups_first_and_can_approve_them(): void
    {
        $member = $this->register()->member;
        $admin = $this->admin();

        $this->assertSame('1', MemberResource::getNavigationBadge());

        $this->actingAs($admin);

        Livewire::test(ListMembers::class)
            ->assertSet('activeTab', 'pending')
            ->assertCanSeeTableRecords([$member])
            ->callTableAction('approve', $member);

        $member->refresh();
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertSame($admin->id, $member->approved_by_user_id);
        $this->assertNull(MemberResource::getNavigationBadge());
    }

    #[Test]
    public function an_approved_member_can_book_and_leaves_the_status_page(): void
    {
        $user = $this->register();
        $user->member->approve();

        $this->actingAs($user);

        $this->get(route('member.pending'))->assertRedirect(route('member.book'));
        $this->get(route('member.book'))->assertOk();

        $this->assertSame(\App\Enums\ReservationStatus::PendingPayment, $this->bookFor($user)->status);
    }

    #[Test]
    public function a_rejected_member_sees_the_reason_and_still_cannot_book(): void
    {
        $member = $this->register()->member;

        $this->actingAs($this->admin());

        Livewire::test(ListMembers::class)
            ->callTableAction('reject', $member, data: ['note' => 'ไม่พบหลักฐานการชำระเงิน']);

        $member->refresh();
        $this->assertSame(MemberStatus::Rejected, $member->status);

        $this->actingAs($member->user);
        $this->get(route('member.book'))->assertRedirect(route('member.pending'));
        $this->get(route('member.pending'))->assertSee('การสมัครยังไม่ผ่าน')->assertSee('ไม่พบหลักฐานการชำระเงิน');
    }

    #[Test]
    public function members_invited_by_a_trainer_skip_approval(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['invite_token' => 'invite-token-123']);

        Livewire::test(TeamJoin::class, ['token' => 'invite-token-123'])
            ->set('first_name', 'ลูก')->set('last_name', 'ทีม')
            ->set('phone', '0811111111')->set('email', 'invited@example.test')
            ->set('password', 'Hybrid#2026ssw')->set('password_confirmation', 'Hybrid#2026ssw')
            ->set('emergency_contact_name', 'แม่')->set('emergency_contact_phone', '0822222222')
            ->set('accept', true)
            ->call('join')
            ->assertHasNoErrors();

        $this->assertSame(MemberStatus::Active, User::where('email', 'invited@example.test')->first()->member->status);
    }

    #[Test]
    public function sign_up_approval_can_be_switched_off(): void
    {
        config(['gym.registration.member_approval' => false]);

        $this->assertSame(MemberStatus::Active, $this->register()->member->status);
    }

    #[Test]
    public function a_pending_member_gets_the_status_link_instead_of_booking_links(): void
    {
        $this->actingAs($this->register());

        $this->get(route('member.pending'))
            ->assertSee('สถานะการสมัคร')
            ->assertDontSee(route('member.book'));
    }
}
