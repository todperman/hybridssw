<?php

namespace Tests\Feature;

use App\Livewire\TrainerRegistration;
use App\Models\User;
use App\Rules\ThaiPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Support\BuildsGym;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneValidationTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected function check(?string $phone): bool
    {
        return Validator::make(['phone' => $phone], ['phone' => [new ThaiPhone]])->passes();
    }

    #[Test]
    public function it_accepts_a_ten_digit_thai_number_however_it_is_typed(): void
    {
        foreach (['0812345678', '081-234-5678', '081 234 5678', '(081) 234-5678'] as $phone) {
            $this->assertTrue($this->check($phone), $phone.' ควรผ่าน');
        }
    }

    #[Test]
    public function it_rejects_the_wrong_length(): void
    {
        foreach (['08123456', '08123456789'] as $phone) {
            $this->assertFalse($this->check($phone), $phone.' ไม่ควรผ่าน');
        }
    }

    #[Test]
    public function it_leaves_an_empty_value_to_the_required_rule(): void
    {
        // ช่องเบอร์ที่ไม่บังคับต้องบันทึกได้ทั้งที่เว้นว่าง
        $this->assertTrue($this->check(null));
        $this->assertTrue($this->check(''));
    }

    #[Test]
    public function it_rejects_a_number_that_does_not_start_with_zero(): void
    {
        $this->assertFalse($this->check('8123456789'));
        $this->assertFalse($this->check('6612345678'));
    }

    #[Test]
    public function it_strips_everything_that_is_not_a_digit(): void
    {
        $this->assertSame('0812345678', ThaiPhone::digits('081-234-5678'));
        $this->assertSame('0812345678', ThaiPhone::digits(' (081) 234 5678 '));
    }

    #[Test]
    public function the_signup_form_rejects_a_bad_phone_and_a_bad_email(): void
    {
        $bad = Livewire::test(TrainerRegistration::class)
            ->set('first_name', 'สมชาย')
            ->set('last_name', 'ใจดี')
            ->set('phone', '08123')
            ->set('email', 'somchai@example')
            ->set('password', 'Password!234')
            ->set('password_confirmation', 'Password!234')
            ->call('next');

        $bad->assertHasErrors(['phone', 'email']);
    }

    #[Test]
    public function the_signup_form_stores_the_phone_as_digits_only(): void
    {
        $branch = $this->makeBranch();
        $email = 'dash'.rand(1000, 9999).'@example.com';

        Livewire::test(TrainerRegistration::class)
            ->set('first_name', 'สมหญิง')
            ->set('last_name', 'ใจงาม')
            ->set('phone', '081-234-5678')
            ->set('email', $email)
            ->set('password', 'Password!234')
            ->set('password_confirmation', 'Password!234')
            ->call('next')
            ->set('branch_id', $branch->id)
            ->set('type', 'external')
            ->call('next')
            ->call('register');

        $this->assertSame('0812345678', User::where('email', $email)->firstOrFail()->phone);
    }
}
