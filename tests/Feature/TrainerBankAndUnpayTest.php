<?php

namespace Tests\Feature;

use App\Enums\EarningStatus;
use App\Enums\TrainerType;
use App\Filament\Pages\TrainerEarnings as AdminPage;
use App\Livewire\Trainer\Earnings as TrainerPage;
use App\Models\AuditLog;
use App\Models\TrainerEarning;
use App\Notifications\AccountNotice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** บัญชีรับเงินของเทรนเนอร์ และปุ่มยกเลิกการจ่าย */
class TrainerBankAndUnpayTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00'));
        Notification::fake();
    }

    #[Test]
    public function a_trainer_saves_a_thai_bank_account_which_is_validated_logged_and_reported_to_admins(): void
    {
        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);
        $trainer = $this->makeAvailableTrainer($gym, ['type' => TrainerType::External]);

        $page = Livewire::actingAs($trainer->user)->test(TrainerPage::class)->call('openBank');

        $page->set('bank_code', 'KBANK')->set('bank_account_number', '123-4-5678')->set('bank_account_name', 'ครูฝน')->call('saveBank')
            ->assertHasErrors(['bank_account_number']);
        $page->set('bank_code', 'XXX')->call('saveBank')->assertHasErrors(['bank_code']);
        $page->set('bank_code', 'KBANK')->set('bank_account_number', '123-4-56789-0')->set('promptpay_id', '12345')->call('saveBank')
            ->assertHasErrors(['promptpay_id']);

        $page->set('promptpay_id', '081-234-5678')->call('saveBank')->assertHasNoErrors()->assertSet('editingBank', false);

        $trainer->refresh();
        $this->assertSame('1234567890', $trainer->bank_account_number, 'เก็บเป็นตัวเลขล้วน');
        $this->assertSame('0812345678', $trainer->promptpay_id);
        $this->assertSame('กสิกรไทย 123-4-56789-0 · ครูฝน · พร้อมเพย์ 0812345678', $trainer->bankLabel());
        $this->assertNotNull($trainer->bank_updated_at);
        $this->assertTrue(AuditLog::where('action', 'trainer.bank_changed')->where('actor_user_id', $trainer->user->id)->exists());
        Notification::assertSentTo($admin, AccountNotice::class, fn (AccountNotice $n) => $n->title === 'เทรนเนอร์เปลี่ยนบัญชีรับเงิน' && str_contains($n->body, '123-4-56789-0'));

        $this->actingAs($trainer->user)->get('/trainer/earnings')->assertOk()->assertSee('123-4-56789-0')->assertSee('กสิกรไทย');
    }

    #[Test]
    public function gsb_accounts_may_have_twelve_digits(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym, ['type' => TrainerType::External]);

        Livewire::actingAs($trainer->user)->test(TrainerPage::class)->call('openBank')
            ->set('bank_code', 'GSB')->set('bank_account_number', '020123456789')->set('bank_account_name', 'ครู')
            ->call('saveBank')->assertHasNoErrors();

        $this->assertSame('020123456789', $trainer->fresh()->bank_account_number);
    }

    #[Test]
    public function admins_see_the_bank_account_when_paying_and_can_undo_a_payout_with_a_reason(): void
    {
        $gym = $this->makeGym(['pricing' => config('gym.pricing'), 'payout' => null]);
        $admin = $this->makeAdmin($gym);
        $trainer = $this->makeAvailableTrainer($gym, ['type' => TrainerType::External, 'bank_code' => 'SCB', 'bank_account_number' => '9876543210', 'bank_account_name' => 'ครูฝน']);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::parse('2026-11-04 18:00')));
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00'));

        $this->actingAs($admin)->get('/admin/trainer-earnings?m=2026-11')->assertOk()->assertSee('ไทยพาณิชย์ 987-6-54321-0')->assertSee('คัดลอกเลขบัญชี');
        $this->actingAs($admin)->get('/admin/trainers')->assertOk();

        $page = Livewire::actingAs($admin)->test(AdminPage::class, ['m' => '2026-11']);
        $page->callAction('pay', ['note' => 'โอนแล้ว'], ['trainer' => $trainer->id]);
        $this->assertSame(EarningStatus::Paid, TrainerEarning::where('reservation_id', $r->id)->sole()->status);

        $page->callAction('unpay', ['reason' => 'โอนผิดบัญชี ธนาคารตีกลับ'], ['trainer' => $trainer->id])->assertNotified();

        $e = TrainerEarning::where('reservation_id', $r->id)->sole();
        $this->assertSame(EarningStatus::Payable, $e->status);
        $this->assertNull($e->paid_at);
        $this->assertNull($e->payout_note);
        $this->assertSame('โอนผิดบัญชี ธนาคารตีกลับ', AuditLog::where('action', 'earning.unpaid')->sole()->reason);
        Notification::assertSentTo($trainer->user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->title, 'ยกเลิกรายการจ่าย') && str_contains($n->body, 'ธนาคารตีกลับ'));

        // ไม่มีบัญชี: หน้าแอดมินเตือน
        $trainer->update(['bank_code' => null, 'bank_account_number' => null]);
        $this->actingAs($admin)->get('/admin/trainer-earnings?m=2026-11')->assertOk()->assertSee('ยังไม่ได้กรอกบัญชีรับเงิน');
    }
}
