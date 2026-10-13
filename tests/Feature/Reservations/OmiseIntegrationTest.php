<?php

namespace Tests\Feature\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Livewire\Reservations\ReservationDetail;
use App\Models\Payment;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ชำระผ่าน Omise ทั้งพร้อมเพย์และบัตร ตั้งแต่เริ่มจ่ายจนยืนยันการจอง */
class OmiseIntegrationTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00'));
        Notification::fake();
        RateLimiter::clear('pay:card:1');

        config([
            'gym.payments.driver' => 'omise',
            'services.omise.public_key' => 'pkey_test_abc',
            'services.omise.secret_key' => 'skey_test_abc',
            'services.omise.methods' => ['promptpay', 'card'],
            'services.omise.webhook_secret' => null,
        ]);
    }

    protected function booking(): Reservation
    {
        $gym = $this->makeGym();

        return $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
    }

    protected function charge(string $id, Reservation $r, string $status, array $extra = []): array
    {
        return array_merge([
            'object' => 'charge',
            'id' => $id,
            'status' => $status,
            'paid' => $status === 'successful',
            'amount' => 80000,
            'currency' => 'thb',
            'metadata' => ['reservation' => $r->reference],
        ], $extra);
    }

    #[Test]
    public function the_payer_gets_one_promptpay_qr_that_expires_with_the_hold(): void
    {
        $r = $this->booking();

        Http::fake(['api.omise.co/charges' => Http::response($this->charge('chrg_qr', $r, 'pending', [
            'expires_at' => $r->hold_expires_at->utc()->toIso8601String(),
            'source' => ['type' => 'promptpay', 'scannable_code' => ['image' => ['download_uri' => 'https://api.omise.co/qr.svg']]],
        ]))]);

        $page = Livewire::actingAs($r->payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->assertSee('พร้อมเพย์')
            ->assertSee('โหมดทดสอบ')
            ->call('startPayment')
            ->assertSee('https://api.omise.co/qr.svg', false);

        // กดซ้ำหรือเปิดหน้าใหม่ได้ QR เดิม ไม่สร้าง charge ใหม่
        $page->call('startPayment');
        Livewire::actingAs($r->payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->assertSee('https://api.omise.co/qr.svg', false);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $req) => $req['amount'] === 80000
            && $req['currency'] === 'thb'
            && $req['source[type]'] === 'promptpay'
            && $req['expires_at'] === $r->hold_expires_at->utc()->format('Y-m-d\TH:i:s\Z')
            && $req['metadata[reservation]'] === $r->reference
            && $req->hasHeader('Omise-Version', '2019-05-29'));

        $this->assertSame(Payment::METHOD_PROMPTPAY, Payment::sole()->method);
    }

    #[Test]
    public function a_card_without_3ds_confirms_immediately_and_keeps_only_brand_and_last_digits(): void
    {
        $r = $this->booking();

        Http::fake(function (Request $req) use ($r) {
            $card = ['brand' => 'Visa', 'last_digits' => '4242', 'name' => 'SOMCHAI JAIDEE'];

            return $req->method() === 'POST'
                ? Http::response($this->charge('chrg_card', $r, 'successful', ['card' => $card]))
                : Http::response($this->charge('chrg_card', $r, 'successful', ['card' => $card]));
        });

        Livewire::actingAs($r->payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('setMethod', 'card')
            ->call('payByCard', 'tokn_test_123')
            ->assertSet('error', null);

        $payment = Payment::sole();
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
        $this->assertSame(Payment::METHOD_CARD, $payment->method);
        $this->assertSame(['brand' => 'Visa', 'last_digits' => '4242'], $payment->payload['card']);
        $this->assertStringContainsString('Visa ••4242', $payment->methodLabel());
        Http::assertSent(fn (Request $req) => $req->method() === 'POST' && $req['card'] === 'tokn_test_123'
            && $req['return_uri'] === route('reservations.payment-return', $r->reference));
    }

    #[Test]
    public function a_3ds_card_goes_to_the_bank_and_is_confirmed_on_return(): void
    {
        $r = $this->booking();
        $paid = false;

        Http::fake(function (Request $req) use ($r, &$paid) {
            if ($req->method() === 'POST') {
                return Http::response($this->charge('chrg_3ds', $r, 'pending', ['authorize_uri' => 'https://bank.example/3ds']));
            }

            return Http::response($this->charge('chrg_3ds', $r, $paid ? 'successful' : 'pending'));
        });

        Livewire::actingAs($r->payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('setMethod', 'card')
            ->call('payByCard', 'tokn_test_3ds')
            ->assertRedirect('https://bank.example/3ds');

        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);

        $paid = true;
        $this->actingAs($r->payer->user)
            ->get(route('reservations.payment-return', $r->reference))
            ->assertRedirect(route('reservations.show', $r->reference))
            ->assertSessionHas('payment_success');

        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
    }

    #[Test]
    public function a_declined_card_shows_why_and_the_booking_stays_payable(): void
    {
        $r = $this->booking();

        Http::fake(['api.omise.co/charges' => Http::response($this->charge('chrg_bad', $r, 'failed', ['failure_code' => 'insufficient_fund']))]);

        Livewire::actingAs($r->payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('setMethod', 'card')
            ->call('payByCard', 'tokn_test_bad')
            ->assertSet('error', fn ($e) => str_contains($e, 'ยอดเงินในบัตรไม่พอ'));

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);
    }

    #[Test]
    public function a_scanned_qr_is_checked_before_the_card_is_charged(): void
    {
        $r = $this->booking();
        $r->payments()->create([
            'payer_member_id' => $r->payer_member_id, 'provider' => 'omise', 'method' => 'promptpay',
            'provider_charge_id' => 'chrg_qr', 'amount' => $r->amount, 'currency' => 'THB', 'status' => PaymentStatus::Pending,
            'payload' => ['qr' => 'https://api.omise.co/qr.svg', 'expires_at' => $r->hold_expires_at->toIso8601String()],
        ]);

        Http::fake(['api.omise.co/charges/chrg_qr' => Http::response($this->charge('chrg_qr', $r, 'successful', ['source' => ['type' => 'promptpay']]))]);

        Livewire::actingAs($r->payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('setMethod', 'card')
            ->assertSee('ไม่ต้องจ่ายด้วยบัตรซ้ำ')
            ->call('payByCard', 'tokn_test_123');

        // QR ถูกจ่ายแล้ว จึงไม่ยิงตัดบัตรเลย
        Http::assertNotSent(fn (Request $req) => $req->method() === 'POST');
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
        $this->assertSame(1, Payment::count());
    }

    #[Test]
    public function only_the_payer_can_pay_and_card_attempts_are_rate_limited(): void
    {
        $gym = $this->makeGym();
        $payer = $this->makeMember($gym);
        $friend = $this->makeMember($gym);
        $r = $this->bookAsTrainee($gym, $friend, [$payer], $this->makeAvailableTrainer($gym));
        $r->update(['payer_member_id' => $payer->id]);

        Http::fake(['api.omise.co/charges' => Http::response($this->charge('chrg_x', $r, 'failed', ['failure_code' => 'payment_rejected']))]);

        Livewire::actingAs($friend->user)->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('payByCard', 'tokn_test_1');
        Http::assertNothingSent();

        $page = Livewire::actingAs($payer->user)->test(ReservationDetail::class, ['reference' => $r->reference])->call('setMethod', 'card');

        foreach (range(1, 6) as $i) {
            $page->call('payByCard', 'tokn_test_'.$i);
        }

        $page->assertSet('error', fn ($e) => str_contains($e, 'ลองบ่อยเกินไป'));
        Http::assertSentCount(5);
    }

    #[Test]
    public function webhook_signatures_are_checked_when_a_secret_is_set(): void
    {
        $r = $this->booking();
        $r->payments()->create([
            'payer_member_id' => $r->payer_member_id, 'provider' => 'omise', 'method' => 'promptpay',
            'provider_charge_id' => 'chrg_wh', 'amount' => $r->amount, 'currency' => 'THB', 'status' => PaymentStatus::Pending,
        ]);
        Http::fake(['api.omise.co/charges/chrg_wh' => Http::response($this->charge('chrg_wh', $r, 'successful'))]);

        $secret = base64_encode('super-secret-key');
        config(['services.omise.webhook_secret' => $secret]);
        $body = json_encode(['key' => 'charge.complete', 'data' => ['id' => 'chrg_wh']]);
        $sign = fn (int $ts, string $key = 'super-secret-key') => hash_hmac('sha256', $ts.'.'.$body, $key);
        $send = fn (array $headers) => $this->call('POST', '/webhooks/omise', [], [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $headers), $body);

        $send(['HTTP_OMISE_SIGNATURE' => $sign(time(), 'wrong-key'), 'HTTP_OMISE_SIGNATURE_TIMESTAMP' => (string) time()])->assertStatus(401);
        $send(['HTTP_OMISE_SIGNATURE' => $sign(time() - 3600), 'HTTP_OMISE_SIGNATURE_TIMESTAMP' => (string) (time() - 3600)])->assertStatus(401);
        $send([])->assertStatus(401);
        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);

        // ช่วงเปลี่ยน secret มีสองลายเซ็น ตรงอันใดอันหนึ่งก็ผ่าน
        $send(['HTTP_OMISE_SIGNATURE' => $sign(time(), 'old-key').','.$sign(time()), 'HTTP_OMISE_SIGNATURE_TIMESTAMP' => (string) time()])->assertOk();
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
    }

    #[Test]
    public function the_sync_job_confirms_payments_whose_webhook_never_arrived(): void
    {
        $r = $this->booking();
        $r->payments()->create([
            'payer_member_id' => $r->payer_member_id, 'provider' => 'omise', 'method' => 'promptpay',
            'provider_charge_id' => 'chrg_quiet', 'amount' => $r->amount, 'currency' => 'THB', 'status' => PaymentStatus::Pending,
        ]);
        $stuck = $r->payments()->create([
            'payer_member_id' => $r->payer_member_id, 'provider' => 'omise', 'method' => 'card',
            'amount' => $r->amount, 'currency' => 'THB', 'status' => PaymentStatus::Pending,
        ]);
        $stuck->forceFill(['created_at' => now()->subMinutes(15)])->save();

        Http::fake(['api.omise.co/charges/chrg_quiet' => Http::response($this->charge('chrg_quiet', $r, 'successful'))]);

        $this->artisan('payments:sync-omise')->assertSuccessful();

        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $stuck->fresh()->status);
    }

    #[Test]
    public function a_charge_that_names_another_booking_is_ignored(): void
    {
        $r = $this->booking();
        $r->payments()->create([
            'payer_member_id' => $r->payer_member_id, 'provider' => 'omise', 'method' => 'promptpay',
            'provider_charge_id' => 'chrg_mix', 'amount' => $r->amount, 'currency' => 'THB', 'status' => PaymentStatus::Pending,
        ]);

        Http::fake(['api.omise.co/charges/chrg_mix' => Http::response($this->charge('chrg_mix', $r, 'successful', ['metadata' => ['reservation' => 'HS-OTHER']]))]);

        $this->postJson('/webhooks/omise', ['key' => 'charge.complete', 'data' => ['id' => 'chrg_mix']])->assertOk();

        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);
    }

    #[Test]
    public function the_doctor_checks_keys_and_enabled_methods_with_omise(): void
    {
        $this->makeAvailableTrainer($this->makeGym());

        Http::fake([
            'api.omise.co/account' => Http::response(['object' => 'account', 'email' => 'owner@example.test']),
            'api.omise.co/capability' => Http::response(['object' => 'capability', 'payment_methods' => [['name' => 'card']]]),
        ]);

        $this->artisan('gym:doctor')
            ->expectsOutputToContain('เชื่อมต่อ Omise ได้')
            ->expectsOutputToContain('Omise ยังเป็นโหมดทดสอบ')
            ->expectsOutputToContain('ยังไม่เปิดช่องทาง: promptpay')
            ->expectsOutputToContain('ยังไม่ได้ตั้ง OMISE_WEBHOOK_SECRET')
            ->assertFailed();

        config(['services.omise.public_key' => 'pkey_live_abc']);
        $this->artisan('gym:doctor')->expectsOutputToContain('ปนกันระหว่างโหมดทดสอบกับโหมดจริง')->assertFailed();
    }
}
