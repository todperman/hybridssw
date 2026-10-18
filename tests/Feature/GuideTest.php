<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** คู่มือการใช้งานหลังล็อกอิน เนื้อหาตามบทบาทและดึงกติกาจริง */
class GuideTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    #[Test]
    public function members_see_their_chapters_and_the_free_benefit_but_not_the_trainer_chapter(): void
    {
        $gym = $this->makeGym(['max_booking_hours' => 3, 'booking_window_days' => 14]);
        config(['gym.reservation.hold_minutes' => 20]);

        $this->actingAs($this->makeMember($gym)->user)->get('/guide')
            ->assertOk()
            ->assertSee('คู่มือการใช้งาน')
            ->assertSee('สิทธิ์ใช้ฟรีสำหรับบุคลากรโรงพยาบาลและแพทย์')
            ->assertSee('จองได้ครั้งละ 1–3 ชม.')
            ->assertSee('ล่วงหน้าไม่เกิน 14 วัน')
            ->assertSee('รอชำระ 20 นาที')
            ->assertDontSee('สำหรับเทรนเนอร์');
    }

    #[Test]
    public function trainers_see_the_trainer_chapter_and_admins_see_everything(): void
    {
        $gym = $this->makeGym();

        $this->actingAs($this->makeAvailableTrainer($gym)->user)->get('/guide')
            ->assertOk()
            ->assertSee('สำหรับเทรนเนอร์')
            ->assertSee('จองยิมให้ลูกเทรน');

        $this->actingAs($this->makeAdmin($gym))->get('/guide')
            ->assertOk()
            ->assertSee('สำหรับเทรนเนอร์')
            ->assertSee('สิทธิ์ใช้ฟรีสำหรับบุคลากรโรงพยาบาลและแพทย์');
    }

    #[Test]
    public function the_guide_is_linked_from_the_navigation_and_needs_a_login(): void
    {
        $this->get('/guide')->assertRedirect(route('login'));

        $gym = $this->makeGym();
        $this->actingAs($this->makeMember($gym)->user)->get('/member/reservations')
            ->assertOk()
            ->assertSee('คู่มือการใช้งาน')
            ->assertSee(route('guide'));
    }

    #[Test]
    public function the_payment_chapter_follows_the_configured_channels(): void
    {
        $gym = $this->makeGym();
        $user = $this->makeMember($gym)->user;

        $this->actingAs($user)->get('/guide')->assertOk()->assertSee('โอนเงินตามยอดที่แสดง');

        config(['gym.payments.driver' => 'omise', 'services.omise.methods' => ['promptpay', 'truemoney']]);
        $this->actingAs($user)->get('/guide')->assertOk()
            ->assertSee('สแกนและโอน')->assertSee('ใส่เบอร์ที่ผูกกับ TrueMoney')->assertDontSee('มือถือเปิดแอป LINE ให้ยืนยัน');
    }

    #[Test]
    public function the_pdf_is_a_real_pdf_in_sarabun_with_the_live_numbers(): void
    {
        $this->get('/guide.pdf')->assertRedirect(route('login'));

        $gym = $this->makeGym(['max_booking_hours' => 3]);
        config(['gym.reservation.hold_minutes' => 25]);

        $response = $this->actingAs($this->makeMember($gym)->user)->get('/guide.pdf');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('THSarabun', $pdf, 'ต้องฝังฟอนต์ TH Sarabun');
        $this->assertGreaterThan(20000, strlen($pdf));
    }
}
