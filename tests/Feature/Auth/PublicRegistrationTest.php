<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\MemberRegistration;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * เดิมไม่มีหน้าสมัครทั่วไป เพราะกลัวได้บัญชีที่ไม่มีทั้งโปรไฟล์เทรนเนอร์และลูกทีม
 * ซึ่งเข้ามาแล้วทำอะไรไม่ได้ ตอนนี้เปิดให้คนทั่วไปสมัครแล้ว
 * จึงยังต้องคุมเงื่อนไขเดิมไว้: สมัครเสร็จต้องได้โปรไฟล์สมาชิกที่ใช้งานได้เสมอ
 */
class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function fill(\Livewire\Features\SupportTesting\Testable $c): \Livewire\Features\SupportTesting\Testable
    {
        return $c->set('first_name', 'สมชาย')
            ->set('last_name', 'ใจดี')
            ->set('phone', '0812345678')
            ->set('email', 'somchai@example.test')
            ->set('password', 'Hybrid#2026ssw')
            ->set('password_confirmation', 'Hybrid#2026ssw')
            ->set('emergency_contact_name', 'สมหญิง ใจดี')
            ->set('emergency_contact_phone', '0898765432')
            ->set('accept', true);
    }

    #[Test]
    public function anyone_can_open_the_registration_page(): void
    {
        Branch::create(['code' => 'B1', 'name' => 'สาขาทดสอบ']);

        $this->get(route('register'))->assertOk()->assertSee('สมัครสมาชิก');
    }

    #[Test]
    public function registering_always_creates_a_usable_member_profile(): void
    {
        $branch = Branch::create(['code' => 'B1', 'name' => 'สาขาทดสอบ']);

        $this->fill(Livewire::test(MemberRegistration::class))
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('member.schedule'));

        $user = User::where('email', 'somchai@example.test')->firstOrFail();

        $this->assertSame(UserRole::Member, $user->role);
        $this->assertNotNull($user->member, 'บัญชีที่ไม่มีโปรไฟล์สมาชิกจะเข้ามาแล้วทำอะไรไม่ได้');
        $this->assertSame($branch->id, $user->member->branch_id);
        $this->assertNull($user->member->primary_trainer_id, 'สมัครเองจึงยังไม่สังกัดเทรนเนอร์คนไหน');
        $this->assertSame('0812345678', $user->phone);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function phone_numbers_are_validated_the_same_as_everywhere_else(): void
    {
        Branch::create(['code' => 'B1', 'name' => 'สาขาทดสอบ']);

        $this->fill(Livewire::test(MemberRegistration::class))
            ->set('phone', '08123')
            ->call('register')
            ->assertHasErrors('phone');

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function public_registration_can_be_switched_off(): void
    {
        config(['gym.registration.allow_public_members' => false]);
        Branch::create(['code' => 'B1', 'name' => 'สาขาทดสอบ']);

        $this->get('/register')->assertNotFound();
    }

    #[Test]
    public function trainers_still_have_their_own_registration_page(): void
    {
        Branch::create(['code' => 'T1', 'name' => 'สาขาทดสอบ']);

        $this->get(route('trainer.register'))
            ->assertOk()
            ->assertSee('สมัครเป็นเทรนเนอร์');
    }
}
