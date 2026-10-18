<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\MailSettings as SettingsPage;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Notifications\AccountNotice;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ตั้งค่า SMTP ผู้ส่ง และอีเมลผู้ดูแลที่รับสำเนา จากหลังบ้าน */
class MailSettingsTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    #[Test]
    public function an_admin_can_open_the_page(): void
    {
        $this->actingAs($this->makeAdmin($this->makeGym()))->get('/admin/mail-settings')->assertOk()->assertSee('อีเมลแจ้งเตือน');
    }

    #[Test]
    public function branch_staff_cannot_open_the_page(): void
    {
        $staff = $this->makeAdmin($this->makeGym());
        $staff->update(['role' => UserRole::Staff]);

        $this->actingAs($staff->fresh())->get('/admin/mail-settings')->assertForbidden();
    }

    #[Test]
    public function saved_settings_override_env_the_password_is_encrypted_and_a_blank_keeps_the_old_one(): void
    {
        $this->actingAs($this->makeAdmin($this->makeGym()));

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'enabled' => true, 'host' => 'smtp.example.test', 'port' => 465, 'encryption' => 'ssl',
                'username' => 'admin@example.test', 'password' => 'super-secret-pass',
                'from_address' => 'admin@example.test', 'from_name' => 'Hybrid SSW ทดสอบ', 'admin_email' => 'inbox@example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('บันทึกแล้ว');

        $raw = AppSetting::find(MailSettings::KEY)->getRawOriginal('value');
        $this->assertStringNotContainsString('super-secret-pass', $raw, 'รหัสผ่านต้องถูกเข้ารหัสก่อนลงฐานข้อมูล');
        $this->assertStringContainsString('smtp.example.test', $raw);

        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('super-secret-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('admin@example.test', config('mail.from.address'));
        $this->assertSame('Hybrid SSW ทดสอบ', config('mail.from.name'));
        $this->assertSame('inbox@example.test', config('gym.notifications.admin_email'));
        $this->assertTrue(AuditLog::where('action', 'mail.settings_changed')->exists());

        // เว้นรหัสผ่านว่าง = คงค่าเดิม
        Livewire::test(SettingsPage::class)
            ->fillForm(['password' => '', 'from_name' => 'ชื่อใหม่'])
            ->call('save')
            ->assertNotified('บันทึกแล้ว');

        $this->assertSame('super-secret-pass', MailSettings::load()['password']);
        $this->assertSame('ชื่อใหม่', MailSettings::load()['from_name']);
    }

    #[Test]
    public function the_admin_copy_address_works_even_while_mail_is_still_sent_through_env(): void
    {
        $this->actingAs($this->makeAdmin($this->makeGym()));

        Livewire::test(SettingsPage::class)
            ->fillForm(['enabled' => false, 'admin_email' => 'inbox@example.test'])
            ->call('save')
            ->assertNotified('บันทึกแล้ว');

        config(['mail.default' => 'array']);
        MailSettings::apply();

        $this->assertSame('array', config('mail.default'), 'ปิดอยู่ต้องไม่แตะ mailer จาก .env');
        $this->assertSame('inbox@example.test', config('gym.notifications.admin_email'));
    }

    #[Test]
    public function the_test_button_sends_one_mail_to_the_given_address_without_saving(): void
    {
        Notification::fake();
        $this->actingAs($this->makeAdmin($this->makeGym()));

        Livewire::test(SettingsPage::class)
            ->fillForm(['enabled' => false, 'from_address' => 'admin@example.test'])
            ->callAction('test', ['to' => 'me@example.test'])
            ->assertNotified('ส่งแล้ว');

        Notification::assertSentOnDemand(AccountNotice::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'me@example.test'
            && $channels === ['mail']
            && $n->title === 'ทดสอบการส่งอีเมล');

        $this->assertNull(AppSetting::find(MailSettings::KEY));
    }
}
