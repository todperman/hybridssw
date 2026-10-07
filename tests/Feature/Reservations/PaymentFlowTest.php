<?php

namespace Tests\Feature\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Models\Payment;
use App\Models\SlotLock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ข้อ 6 การชำระเงินและการกัน Slot */
class PaymentFlowTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));
        Notification::fake();
    }

    // --- หมดเวลา ---

    #[Test]
    public function an_unpaid_hold_expires_and_frees_every_slot(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->travel(31)->minutes();
        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $r->refresh();
        $this->assertSame(ReservationStatus::Expired, $r->status);
        $this->assertSame(0, SlotLock::count());

        $again = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));
        $this->assertSame(ReservationStatus::PendingPayment, $again->status);
    }

    #[Test]
    public function a_stale_hold_never_blocks_a_new_booking_even_before_the_job_runs(): void
    {
        $gym = $this->makeGym();
        $old = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->travel(31)->minutes();

        $new = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->assertSame(ReservationStatus::PendingPayment, $new->status);
        $this->assertSame(ReservationStatus::Expired, $old->fresh()->status);
    }

    #[Test]
    public function an_active_hold_is_never_released_early(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->travel(29)->minutes();
        $this->artisan('reservations:expire-holds');

        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);
    }

    // --- ชำระสำเร็จ ---

    #[Test]
    public function recording_payment_confirms_the_booking_without_waiting_for_the_trainer(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $r = $this->confirmManually($r, $this->makeAdmin($gym));

        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertNull($r->hold_expires_at);
        $this->assertSame(3, SlotLock::where('reservation_id', $r->id)->count(), 'ยืนยันแล้วยังถือล็อกเดิม');
    }

    #[Test]
    public function a_payment_cannot_be_recorded_twice(): void
    {
        $gym = $this->makeGym();
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym)));

        $this->expectExceptionObject(ReservationException::alreadyPaid());

        $this->payments()->recordSuccess($r, Payment::PROVIDER_MANUAL, $r->amount);
    }

    #[Test]
    public function the_recorded_amount_must_match_the_booking(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->expectExceptionObject(ReservationException::amountMismatch());

        $this->payments()->recordSuccess($r, Payment::PROVIDER_MANUAL, '500.00');
    }

    #[Test]
    public function a_late_payment_still_confirms_when_the_time_is_still_free(): void
    {
        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->travel(31)->minutes();
        $this->artisan('reservations:expire-holds');
        $this->assertSame(0, SlotLock::count());

        $r = $this->confirmManually($r);

        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame(3, SlotLock::where('reservation_id', $r->id)->count(), 'ล็อกเวลากลับคืนมาครบ');
    }

    #[Test]
    public function a_late_payment_never_overrides_someone_who_booked_the_time_since(): void
    {
        $gym = $this->makeGym();
        $first = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        $this->travel(31)->minutes();
        $second = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym)));

        $payment = $this->payments()->recordSuccess($first, Payment::PROVIDER_MANUAL, $first->amount);

        $this->assertSame(ReservationStatus::Expired, $first->fresh()->status);
        $this->assertTrue($payment->needs_review, 'ต้องให้แอดมินตรวจและคืนเงิน');
        $this->assertSame(ReservationStatus::Confirmed, $second->fresh()->status);
        $this->assertSame(0, SlotLock::where('reservation_id', $first->id)->count());
    }

    // --- Omise ---

    protected function omiseCharge(string $id, string $reference, string $status = 'successful', int $amount = 80000): array
    {
        return [
            'object' => 'charge',
            'id' => $id,
            'status' => $status,
            'paid' => $status === 'successful',
            'amount' => $amount,
            'metadata' => ['reservation' => $reference],
        ];
    }

    #[Test]
    public function the_omise_webhook_is_verified_with_omise_before_confirming(): void
    {
        config(['services.omise.secret_key' => 'skey_test_x']);

        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        Http::fake(['api.omise.co/charges/chrg_1' => Http::response($this->omiseCharge('chrg_1', $r->reference))]);

        // payload ของ webhook บอกว่าสำเร็จ แต่ระบบต้องไปถาม Omise เองก่อนเชื่อ
        $this->postJson('/webhooks/omise', ['key' => 'charge.complete', 'data' => ['id' => 'chrg_1']])->assertOk();

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/charges/chrg_1'));
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
    }

    #[Test]
    public function the_same_omise_charge_is_only_recorded_once(): void
    {
        config(['services.omise.secret_key' => 'skey_test_x']);

        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        Http::fake(['api.omise.co/charges/chrg_1' => Http::response($this->omiseCharge('chrg_1', $r->reference))]);

        foreach (range(1, 3) as $i) {
            $this->postJson('/webhooks/omise', ['key' => 'charge.complete', 'data' => ['id' => 'chrg_1']])->assertOk();
        }

        $this->assertSame(1, Payment::where('provider_charge_id', 'chrg_1')->count());
        $this->assertSame(1, Payment::where('status', PaymentStatus::Succeeded->value)->count());
    }

    #[Test]
    public function a_failed_omise_charge_never_confirms(): void
    {
        config(['services.omise.secret_key' => 'skey_test_x']);

        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        Http::fake(['api.omise.co/charges/chrg_2' => Http::response($this->omiseCharge('chrg_2', $r->reference, 'failed'))]);

        $this->postJson('/webhooks/omise', ['key' => 'charge.complete', 'data' => ['id' => 'chrg_2']])->assertOk();

        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);
    }

    #[Test]
    public function a_forged_webhook_cannot_confirm_a_booking(): void
    {
        config(['services.omise.secret_key' => 'skey_test_x']);

        $gym = $this->makeGym();
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $this->makeAvailableTrainer($gym));

        // Omise บอกว่า charge นี้ยังไม่ได้จ่าย ทั้งที่คนปลอม webhook อ้างว่าจ่ายแล้ว
        Http::fake(['api.omise.co/charges/chrg_fake' => Http::response($this->omiseCharge('chrg_fake', $r->reference, 'pending'))]);

        $this->postJson('/webhooks/omise', [
            'key' => 'charge.complete',
            'data' => ['id' => 'chrg_fake', 'status' => 'successful', 'paid' => true],
        ])->assertOk();

        $this->assertSame(ReservationStatus::PendingPayment, $r->fresh()->status);
    }
}
