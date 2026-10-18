<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\TrainerStatus;
use App\Livewire\MemberRegistration;
use App\Livewire\TeamJoin;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Notifications\ReservationNotice;
use App\Services\Notify\AccountNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** อีเมล/กระดิ่งเรื่องบัญชี และสำเนาทุกเหตุการณ์เข้ากล่องผู้ดูแล */
class AccountNoticeTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['gym.notifications.admin_email' => 'inbox@example.test']);
    }

    protected function register(string $email = 'somchai@example.test'): User
    {
        Livewire::test(MemberRegistration::class)
            ->set('first_name', 'สมชาย')->set('last_name', 'ใจดี')
            ->set('phone', '0812345678')->set('email', $email)
            ->set('password', 'Hybrid#2026ssw')->set('password_confirmation', 'Hybrid#2026ssw')
            ->set('emergency_contact_name', 'สมหญิง')->set('emergency_contact_phone', '0898765432')
            ->set('accept', true)
            ->call('register')
            ->assertHasNoErrors();

        return User::where('email', $email)->firstOrFail();
    }

    #[Test]
    public function registering_welcomes_the_member_tells_admins_and_copies_the_admin_inbox_once(): void
    {
        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        config(['gym.registration.member_approval' => true]);

        $user = $this->register();

        Notification::assertSentTo($user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->title, 'ยินดีต้อนรับ')
            && str_contains($n->body, 'กำลังตรวจสอบ') && $n->url === route('member.pending'));

        Notification::assertSentTo($admin, AccountNotice::class, fn (AccountNotice $n) => $n->title === 'สมาชิกใหม่รอการอนุมัติ'
            && str_contains($n->body, 'somchai@example.test') && $n->url === url('/admin/members'));

        // สำเนาเข้ากล่องผู้ดูแลเฉพาะฉบับ "มีคนสมัคร" ไม่ใช่ฉบับต้อนรับ และมีแต่ช่องทางเมล
        Notification::assertSentOnDemandTimes(AccountNotice::class, 1);
        Notification::assertSentOnDemand(AccountNotice::class, fn (AccountNotice $n, array $channels, $notifiable) => $notifiable->routes['mail'] === 'inbox@example.test'
            && $channels === ['mail'] && $n->adminCopy && $n->title === 'สมาชิกใหม่รอการอนุมัติ'
            && $n->recipients === [$admin->displayName()]);
    }

    #[Test]
    public function approving_and_rejecting_tell_the_member_and_leave_an_audit_trail(): void
    {
        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        $member = $this->makeMember($gym, null, ['status' => MemberStatus::Pending, 'approved_at' => null]);

        $member->approve($admin);
        Notification::assertSentTo($member->user, AccountNotice::class, fn (AccountNotice $n) => $n->title === 'อนุมัติการสมัครแล้ว' && $n->url === route('member.book'));
        $this->assertTrue(AuditLog::where('action', 'member.approved')->where('actor_user_id', $admin->id)->exists());

        $member->reject($admin, 'เอกสารไม่ครบ');
        Notification::assertSentTo($member->user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->title, 'ยังไม่ผ่าน') && str_contains($n->body, 'เอกสารไม่ครบ'));
        $this->assertSame('เอกสารไม่ครบ', AuditLog::where('action', 'member.rejected')->sole()->reason);
    }

    #[Test]
    public function trainer_approval_goes_through_the_model_and_notifies_the_trainer(): void
    {
        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        $trainer = $this->makeAvailableTrainer($gym, ['status' => TrainerStatus::Pending, 'approved_at' => null]);

        $trainer->approve($admin);
        $trainer->refresh();

        $this->assertSame(TrainerStatus::Approved, $trainer->status);
        $this->assertSame($admin->id, $trainer->approved_by);
        $this->assertNotNull($trainer->approved_at);
        Notification::assertSentTo($trainer->user, AccountNotice::class, fn (AccountNotice $n) => $n->title === 'อนุมัติเทรนเนอร์แล้ว' && $n->url === route('trainer.jobs'));

        $trainer->reject($admin, 'ใบรับรองอ่านไม่ออก');
        $this->assertSame(TrainerStatus::Rejected, $trainer->fresh()->status);
        $this->assertSame('ใบรับรองอ่านไม่ออก', $trainer->fresh()->review_note);
        Notification::assertSentTo($trainer->user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->body, 'ใบรับรองอ่านไม่ออก'));
    }

    #[Test]
    public function joining_a_team_welcomes_the_member_and_tells_the_trainer(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym, ['invite_token' => 'invite-token-123']);

        Livewire::test(TeamJoin::class, ['token' => 'invite-token-123'])
            ->set('first_name', 'ลูก')->set('last_name', 'ทีม')
            ->set('phone', '0811111111')->set('email', 'invited@example.test')
            ->set('password', 'Hybrid#2026ssw')->set('password_confirmation', 'Hybrid#2026ssw')
            ->set('emergency_contact_name', 'แม่')->set('emergency_contact_phone', '0822222222')
            ->set('accept', true)
            ->call('join')
            ->assertHasNoErrors();

        $user = User::where('email', 'invited@example.test')->firstOrFail();

        Notification::assertSentTo($user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->title, 'ยินดีต้อนรับ') && str_contains($n->body, $trainer->user->displayName()));
        Notification::assertSentTo($trainer->user, AccountNotice::class, fn (AccountNotice $n) => $n->title === 'ลูกทีมใหม่เข้าทีมของคุณ' && $n->url === route('trainer.team'));
        Notification::assertSentOnDemand(AccountNotice::class, fn (AccountNotice $n) => $n->adminCopy && $n->title === 'ลูกทีมใหม่เข้าทีมของคุณ');
    }

    #[Test]
    public function the_admin_copy_is_skipped_when_the_admin_already_receives_it_or_the_event_is_demo(): void
    {
        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        config(['gym.notifications.admin_email' => $admin->email]);

        $this->register();
        Notification::assertSentTo($admin, AccountNotice::class);
        Notification::assertSentOnDemandTimes(AccountNotice::class, 0);

        config(['gym.notifications.admin_email' => 'inbox@example.test']);
        $demo = $this->makeMember($gym);
        $demo->user->update(['email' => 'member9@'.\App\Services\DemoData::DOMAIN]);

        app(AccountNotifier::class)->memberApproved($demo->fresh());

        Notification::assertSentTo($demo->user->fresh(), AccountNotice::class, fn ($n, array $channels) => $channels === ['database'], 'บัญชีตัวอย่างต้องไม่ได้รับเมล');
        Notification::assertSentOnDemandTimes(AccountNotice::class, 0);
    }

    #[Test]
    public function reservation_events_are_copied_to_the_admin_inbox_with_the_real_recipients_named(): void
    {
        $gym = $this->makeGym();
        $booker = $this->makeMember($gym);

        $reservation = $this->bookAsTrainee($gym, $booker, [], $this->makeAvailableTrainer($gym));

        Notification::assertSentTo($booker->user, ReservationNotice::class, fn (ReservationNotice $n) => $n->title === 'มีรายการรอชำระเงิน');
        Notification::assertSentOnDemand(ReservationNotice::class, fn (ReservationNotice $n, array $channels, $notifiable) => $notifiable->routes['mail'] === 'inbox@example.test'
            && $n->adminCopy && $n->reference === $reservation->reference
            && $n->recipients === [$booker->user->displayName()]);
    }

    #[Test]
    public function the_mail_is_branded_in_thai_and_the_admin_copy_says_who_it_was_for(): void
    {
        $notice = (new AccountNotice('หัวข้อทดสอบ', 'เนื้อหาทดสอบ', 'https://example.test/x', 'HS261018ABCDE'))->asAdminCopy(['สมชาย', 'สมหญิง']);
        $mail = $notice->toMail(Notification::route('mail', 'inbox@example.test'));

        $this->assertSame('[สำเนาผู้ดูแล] หัวข้อทดสอบ · HS261018ABCDE', $mail->subject);

        $html = $mail->render()->toHtml();
        $this->assertStringContainsString('img/brand.png', $html, 'หัวจดหมายต้องมีโลโก้');
        $this->assertStringContainsString('สมชาย, สมหญิง', $html);
        $this->assertStringContainsString('ดูรายละเอียด', $html);
        $this->assertStringContainsString('ถ้ากดปุ่ม', $html, 'ข้อความสำรองใต้ปุ่มต้องเป็นภาษาไทย');
        $this->assertStringNotContainsString('Regards', $html);

        $gym = $this->makeGym();
        $member = $this->makeMember($gym);
        $direct = (new AccountNotice('หัวข้อ', 'เนื้อหา'))->toMail($member->user);
        $this->assertStringContainsString('สวัสดีคุณ'.$member->user->displayName(), $direct->render()->toHtml());
    }
}
