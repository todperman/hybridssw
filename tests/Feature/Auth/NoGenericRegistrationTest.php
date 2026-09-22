<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoGenericRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_generic_registration_page_does_not_exist(): void
    {
        // ปิดทิ้งโดยตั้งใจ เพราะสร้างบัญชีที่ไม่มีโปรไฟล์เทรนเนอร์หรือลูกทีม
        // ซึ่งเข้ามาแล้วใช้งานอะไรไม่ได้
        $this->get('/register')->assertNotFound();
    }

    #[Test]
    public function the_register_route_name_is_no_longer_registered(): void
    {
        $this->assertFalse(
            app('router')->has('register'),
            'ถ้าเส้นทางนี้กลับมา โค้ดที่เรียก route("register") จะพาไปหน้าที่ไม่ควรมี',
        );
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
