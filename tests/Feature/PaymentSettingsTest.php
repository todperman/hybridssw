<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\PaymentSettings as SettingsPage;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Services\Payments\OmiseGateway;
use App\Services\Payments\PaymentGateways;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ตั้งค่าชำระเงินและคีย์ Omise จากหลังบ้าน */
class PaymentSettingsTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    #[Test]
    public function an_admin_can_open_the_page(): void
    {
        $this->actingAs($this->makeAdmin($this->makeGym()))->get('/admin/payment-settings')->assertOk();
    }

    #[Test]
    public function branch_staff_cannot_open_the_page(): void
    {
        $staff = $this->makeAdmin($this->makeGym());
        $staff->update(['role' => UserRole::Staff]);

        $this->actingAs($staff->fresh())->get('/admin/payment-settings')->assertForbidden();
    }

    #[Test]
    public function saved_settings_override_env_secrets_are_encrypted_and_blanks_keep_old_values(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'driver' => 'omise', 'mode' => 'test', 'methods' => ['promptpay', 'card'],
                'test' => ['public_key' => 'pkey_test_abc', 'secret_key' => 'skey_test_secret1', 'webhook_secret' => 'wh-secret-1'],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('บันทึกแล้ว');

        $raw = AppSetting::withoutGlobalScopes()->find(PaymentSettings::KEY)->getRawOriginal('value');
        $this->assertStringNotContainsString('skey_test_secret1', $raw, 'คีย์ลับต้องถูกเข้ารหัสก่อนลงฐานข้อมูล');
        $this->assertStringNotContainsString('wh-secret-1', $raw);
        $this->assertStringContainsString('pkey_test_abc', $raw, 'public key ไม่ใช่ความลับ');

        PaymentSettings::apply();
        $this->assertSame('omise', config('gym.payments.driver'));
        $this->assertSame('skey_test_secret1', config('services.omise.secret_key'));
        $this->assertSame('wh-secret-1', config('services.omise.webhook_secret'));
        $this->assertSame(['promptpay', 'card'], config('services.omise.methods'));
        $this->assertInstanceOf(OmiseGateway::class, app(PaymentGateways::class)->current());

        // บันทึกอีกครั้งโดยเว้นช่องคีย์ลับว่าง ต้องคงค่าเดิม และประวัติบอกว่าเปลี่ยนแค่ public key
        Livewire::test(SettingsPage::class)
            ->fillForm(['test' => ['public_key' => 'pkey_test_xyz', 'secret_key' => '', 'webhook_secret' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('skey_test_secret1', PaymentSettings::load()['test']['secret_key']);
        $this->assertSame(['changed' => ['test.public_key']], AuditLog::where('action', 'payments.settings_changed')->latest('id')->first()->after);

        // คีย์ลับไม่ถูกส่งกลับมาแสดงในฟอร์ม
        Livewire::test(SettingsPage::class)->assertFormSet(['test.secret_key' => null]);
    }

    #[Test]
    public function keys_of_the_wrong_mode_are_refused(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'driver' => 'omise', 'mode' => 'live', 'methods' => ['card'],
                'live' => ['public_key' => 'pkey_test_abc', 'secret_key' => 'skey_test_abc'],
            ])
            ->call('save')
            ->assertNotified('บันทึกไม่ได้');

        $this->assertNull(AppSetting::find(PaymentSettings::KEY));
    }

    #[Test]
    public function the_connection_test_talks_to_omise_with_the_keys_in_the_form(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));

        Http::fake([
            'api.omise.co/account' => Http::response(['object' => 'account', 'email' => 'owner@example.test']),
            'api.omise.co/capability' => Http::response(['object' => 'capability', 'payment_methods' => [['name' => 'card'], ['name' => 'truemoney']]]),
        ]);

        Livewire::test(SettingsPage::class)
            ->fillForm([
                'driver' => 'omise', 'mode' => 'test', 'methods' => ['promptpay', 'card', 'truemoney'],
                'test' => ['public_key' => 'pkey_test_abc', 'secret_key' => 'skey_test_abc'],
            ])
            ->callAction('test')
            ->assertNotified();

        Http::assertSent(fn ($req) => str_contains($req->url(), '/account') && $req->hasHeader('Authorization'));
    }
}
