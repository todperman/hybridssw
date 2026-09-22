<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNameTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_full_name_is_composed_by_the_database(): void
    {
        $user = User::create([
            'first_name' => 'ทดสอบ',
            'last_name' => 'นามสกุล',
            'email' => 'compose@example.test',
            'password' => 'password',
        ]);

        // name เป็น stored generated column ค่าจึงมาหลังอ่านกลับจากฐานข้อมูล
        $this->assertSame('ทดสอบ นามสกุล', $user->fresh()->name);
    }

    #[Test]
    public function it_trims_the_gap_when_there_is_no_last_name(): void
    {
        $user = User::create([
            'first_name' => 'เดี่ยว',
            'last_name' => '',
            'email' => 'single@example.test',
            'password' => 'password',
        ]);

        $this->assertSame('เดี่ยว', $user->fresh()->name);
    }

    #[Test]
    public function the_full_name_follows_a_rename(): void
    {
        $user = User::create([
            'first_name' => 'ก่อน',
            'last_name' => 'แก้ไข',
            'email' => 'rename@example.test',
            'password' => 'password',
        ]);

        $user->update(['first_name' => 'หลัง', 'last_name' => 'แก้ไขแล้ว']);

        $this->assertSame('หลัง แก้ไขแล้ว', $user->fresh()->name);
    }

    #[Test]
    public function the_full_name_cannot_be_written_to_directly(): void
    {
        // กันไม่ให้โค้ดเก่าที่ยังเขียน name เข้ามาแบบเงียบๆ แล้วข้อมูลเพี้ยน
        $user = User::create([
            'first_name' => 'อ่าน',
            'last_name' => 'อย่างเดียว',
            'email' => 'readonly@example.test',
            'password' => 'password',
        ]);

        $this->expectException(QueryException::class);

        User::where('id', $user->id)->update(['name' => 'เขียนทับ']);
    }

    #[Test]
    public function users_can_be_searched_and_sorted_by_full_name(): void
    {
        // ข้อดีของการใช้ generated column แทน accessor คือค้นหาและเรียงลำดับใน SQL ได้
        User::create(['first_name' => 'สมชาย', 'last_name' => 'ใจดี', 'email' => 'a@example.test', 'password' => 'password']);
        User::create(['first_name' => 'สมหญิง', 'last_name' => 'ใจงาม', 'email' => 'b@example.test', 'password' => 'password']);

        $found = User::where('name', 'like', '%ใจดี%')->pluck('email');

        $this->assertSame(['a@example.test'], $found->all());
        $this->assertSame(2, User::whereIn('email', ['a@example.test', 'b@example.test'])->orderBy('name')->count());
    }
}
