<?php

namespace Tests\Feature\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Livewire\Reservations\BookingWizard;
use App\Models\Coupon;
use App\Models\MemberType;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** คูปองส่วนลด: หักจากยอดหลังสิทธิ์ฟรี ตรวจทุกเงื่อนไขตอนบันทึก นับครั้งจากการจองที่ยังถือเวลา */
class CouponTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00'));
        Notification::fake();
    }

    protected function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'welcome10', 'name' => 'ต้อนรับ', 'type' => Coupon::TYPE_PERCENT, 'value' => 10,
        ], $attributes));
    }

    protected function book($gym, $member, ?string $code, $start = null, array $others = [], $trainer = null, int $hours = 1): Reservation
    {
        return $this->reservations()->create(
            $gym, $start ?? $this->tomorrowAt(), $hours, array_merge([$member->id], array_map(fn ($m) => $m->id, $others)), $member->id,
            $trainer ?? $this->makeAvailableTrainer($gym), $member->user, Reservation::VIA_TRAINEE, $code,
        );
    }

    #[Test]
    public function codes_are_stored_upper_case_and_matched_loosely(): void
    {
        $coupon = $this->coupon(['code' => ' welcome 10 ']);

        $this->assertSame('WELCOME10', $coupon->code);
        $this->assertTrue(Coupon::findByCode('welcome10')->is($coupon));
        $this->assertNull(Coupon::findByCode('nope'));
    }

    #[Test]
    public function a_percent_coupon_with_a_cap_and_a_fixed_coupon_discount_the_group_total(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $me = $this->makeMember($gym);
        $this->coupon(['code' => 'HALF', 'type' => 'percent', 'value' => 50, 'max_discount' => 300]);
        $this->coupon(['code' => 'MINUS200', 'type' => 'fixed', 'value' => 200]);

        // 1 คน มี Trainer 2 ชม. = 2,100 → 50% = 1,050 แต่เพดาน 300
        $r = $this->book($gym, $me, 'half', hours: 2);
        $this->assertSame('1800.00', (string) $r->amount);
        $this->assertSame('300.00', (string) $r->discount_amount);
        $this->assertSame('HALF', $r->pricing['coupon']);
        $this->assertStringContainsString('โค้ด HALF −฿300', $r->priceLabel());

        $r2 = $this->book($gym, $this->makeMember($gym), 'minus200', CarbonImmutable::parse('2026-10-17 10:00'));
        $this->assertSame('850.00', (string) $r2->amount);
    }

    #[Test]
    public function the_discount_comes_off_what_is_left_after_free_shares(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $staff = $this->makeMember($gym);
        $staff->setMemberType(MemberType::where('code', 'hospital_staff')->sole(), null, $this->makeAdmin($gym), 'ทดสอบ');
        $friend = $this->makeMember($gym);
        $this->coupon(['code' => 'TEN', 'value' => 10]);

        // 2 คน มี Trainer 1,550 บุคลากรฟรี 775 เหลือ 775 → ลด 10% = 77.50
        $r = $this->book($gym, $friend, 'ten', others: [$staff->fresh()]);

        $this->assertSame('697.50', (string) $r->amount);
        $this->assertSame('77.50', (string) $r->discount_amount);
    }

    #[Test]
    public function a_full_discount_confirms_without_payment_and_a_tiny_remainder_is_refused(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $this->coupon(['code' => 'FREE', 'value' => 100]);
        $this->coupon(['code' => 'ALMOST', 'type' => 'fixed', 'value' => 1040]);

        $r = $this->book($gym, $this->makeMember($gym), 'free');
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame('0.00', (string) $r->amount);

        try {
            $this->book($gym, $this->makeMember($gym), 'almost', CarbonImmutable::parse('2026-10-17 10:00'));
            $this->fail('เหลือ 10 บาท ต่ำกว่าขั้นต่ำของช่องทางชำระ ต้องปฏิเสธ');
        } catch (ReservationException $e) {
            $this->assertSame('coupon_too_small', $e->reason);
        }
    }

    #[Test]
    public function every_rule_is_enforced_on_save(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $other = $this->makeGym(['code' => 'OTHER', 'pricing' => null]);
        $me = $this->makeMember($gym);

        $cases = [
            'coupon_unknown' => null,
            'coupon_inactive' => ['code' => 'OFF', 'is_active' => false],
            'coupon_inactive_expired' => ['code' => 'OLD', 'expires_at' => '2026-10-14 00:00'],
            'coupon_inactive_future' => ['code' => 'SOON', 'starts_at' => '2026-11-01 00:00'],
            'coupon_branch' => ['code' => 'ELSEWHERE', 'branch_id' => $other->id],
            'coupon_mode' => ['code' => 'NOTRAINER', 'applies_to' => Coupon::APPLIES_WITHOUT_TRAINER],
            'coupon_min' => ['code' => 'BIG', 'min_amount' => 5000],
        ];

        foreach ($cases as $expected => $attrs) {
            if ($attrs) {
                $this->coupon($attrs);
            }

            try {
                $this->book($gym, $me, $attrs['code'] ?? 'NOPE');
                $this->fail("$expected ต้องถูกปฏิเสธ");
            } catch (ReservationException $e) {
                $this->assertSame(str_replace(['_expired', '_future'], '', $expected), $e->reason, $expected);
            }
        }

        $this->assertSame(0, Reservation::count());
    }

    #[Test]
    public function uses_are_counted_from_live_bookings_only(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $coupon = $this->coupon(['code' => 'TWICE', 'max_uses' => 2, 'max_uses_per_member' => 1]);

        $first = $this->book($gym, $me, 'twice', CarbonImmutable::parse('2026-10-16 10:00'), trainer: $trainer);
        $this->assertSame(1, $coupon->usedCount());

        try {
            $this->book($gym, $me, 'twice', CarbonImmutable::parse('2026-10-16 12:00'), trainer: $trainer);
            $this->fail('คนเดิมใช้ซ้ำไม่ได้');
        } catch (ReservationException $e) {
            $this->assertSame('coupon_member_limit', $e->reason);
        }

        $second = $this->confirmManually($this->book($gym, $this->makeMember($gym), 'twice', CarbonImmutable::parse('2026-10-16 14:00'), trainer: $trainer));

        try {
            $this->book($gym, $this->makeMember($gym), 'twice', CarbonImmutable::parse('2026-10-16 16:00'), trainer: $trainer);
            $this->fail('ครบ 2 ครั้งแล้ว');
        } catch (ReservationException $e) {
            $this->assertSame('coupon_exhausted', $e->reason);
        }

        // รายการแรกไม่ชำระจนหมดอายุ → คืนสิทธิ์ ส่วนรายการที่สองชำระแล้วยังนับอยู่
        $this->travel(31)->minutes();
        $this->reservations()->expireStaleHolds();
        $this->assertSame(ReservationStatus::Expired, $first->fresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $second->fresh()->status);
        $this->assertSame(1, $coupon->usedCount());
        $this->book($gym, $this->makeMember($gym), 'twice', CarbonImmutable::parse('2026-10-16 16:00'), trainer: $trainer);
    }

    #[Test]
    public function the_booking_page_applies_removes_and_re_checks_a_code_live(): void
    {
        $gym = $this->makeGym(['pricing' => null]);
        $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $this->coupon(['code' => 'TEN', 'value' => 10, 'applies_to' => Coupon::APPLIES_WITH_TRAINER]);

        $page = Livewire::actingAs($me->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-16')
            ->set('couponCode', 'bogus')
            ->call('applyCoupon')
            ->assertSet('couponApplied', null)
            ->assertSet('couponError', 'ไม่พบโค้ดส่วนลดนี้')
            ->set('couponCode', 'ten')
            ->call('applyCoupon')
            ->assertSet('couponApplied', 'TEN')
            ->assertSet('couponError', null)
            ->assertSee('ลด ฿105');

        $this->assertSame(945.0, $page->instance()->quote->amount);

        $page->call('removeCoupon')->assertSet('couponApplied', null);
        $this->assertSame(1050.0, $page->instance()->quote->amount);

        // ใช้โค้ดแล้วเปลี่ยนเป็นไม่มี Trainer โค้ดใช้ไม่ได้แล้ว ต้องถอดออกและบอก
        $me->update(['can_book_without_trainer' => true]);
        $page = Livewire::actingAs($me->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-16')
            ->set('couponCode', 'ten')->call('applyCoupon')->assertSet('couponApplied', 'TEN')
            ->call('chooseTrainer', null);
        $page->instance()->quote;
        $this->assertNull($page->instance()->couponApplied);
        $this->assertStringContainsString('เฉพาะการจองที่มี Trainer', $page->instance()->couponError);
    }

    #[Test]
    public function an_admin_creates_a_coupon_from_the_back_office(): void
    {
        $gym = $this->makeGym();
        $admin = $this->makeAdmin($gym);

        $this->actingAs($admin);
        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'launch 20', 'name' => 'เปิดตัว', 'type' => 'percent', 'value' => 20, 'max_discount' => 500,
                'min_amount' => 0, 'applies_to' => 'both', 'max_uses' => 100, 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::sole();
        $this->assertSame('LAUNCH20', $coupon->code);
        $this->assertSame($admin->id, $coupon->created_by_user_id);
        $this->assertSame('20% (สูงสุด ฿500)', $coupon->discountLabel());

        $this->get('/admin/coupons')->assertOk();
    }
}
