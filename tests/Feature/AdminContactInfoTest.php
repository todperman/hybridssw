<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Resources\Trainers\Pages\ListTrainers;
use App\Rules\ThaiPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** เบอร์โทรและอีเมลที่กรอกตอนสมัครต้องเห็น ค้นได้ และแก้ได้จากหลังบ้าน */
class AdminContactInfoTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    #[Test]
    public function phone_numbers_format_for_reading(): void
    {
        $this->assertSame('081-234-5678', ThaiPhone::format('0812345678'));
        $this->assertSame('081-234-5678', ThaiPhone::format('081-234-5678'));
        $this->assertSame('02-123-4567', ThaiPhone::format('021234567'));
        $this->assertNull(ThaiPhone::format(null));
        $this->assertNull(ThaiPhone::format(''));
    }

    #[Test]
    public function the_members_table_shows_phone_and_email_and_finds_members_by_phone(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));
        $member = $this->makeMember($gym);
        $member->user->update(['phone' => '0812345678', 'email' => 'contact@example.test', 'nickname' => 'ต้น']);
        $other = $this->makeMember($gym);
        $other->user->update(['phone' => '0899999999']);

        $this->get('/admin/members')->assertOk()->assertSee('081-234-5678')->assertSee('contact@example.test');

        Livewire::test(ListMembers::class)
            ->searchTable('081-234-5678')
            ->assertCanSeeTableRecords([$member])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ListMembers::class)
            ->searchTable('contact@example')
            ->assertCanSeeTableRecords([$member])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function the_member_edit_page_shows_and_saves_contact_details_on_the_user(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));
        $member = $this->makeMember($gym);
        $member->user->update(['phone' => '0812345678', 'email' => 'before@example.test']);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertFormSet(['user.phone' => '0812345678', 'user.email' => 'before@example.test'])
            ->fillForm(['user' => ['phone' => '089-000-1234', 'email' => 'after@example.test', 'nickname' => 'ใหม่']])
            ->call('save')
            ->assertHasNoFormErrors();

        $user = $member->user->fresh();
        $this->assertSame('0890001234', $user->phone, 'เก็บเป็นตัวเลขล้วนเหมือนตอนสมัคร');
        $this->assertSame('after@example.test', $user->email);
        $this->assertSame('ใหม่', $user->nickname);
    }

    #[Test]
    public function a_phone_or_email_already_used_by_someone_else_is_rejected(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));
        $member = $this->makeMember($gym);
        $other = $this->makeMember($gym);
        $other->user->update(['phone' => '0877777777', 'email' => 'taken@example.test']);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm(['user' => ['phone' => '0877777777', 'email' => 'taken@example.test']])
            ->call('save')
            ->assertHasFormErrors(['user.phone', 'user.email']);
    }

    #[Test]
    public function the_trainers_table_and_edit_page_carry_contact_details_too(): void
    {
        $gym = $this->makeGym();
        $this->actingAs($this->makeAdmin($gym));
        $trainer = $this->makeAvailableTrainer($gym);
        $trainer->user->update(['phone' => '0861112222', 'email' => 'coach@example.test']);

        $this->get('/admin/trainers')->assertOk()->assertSee('086-111-2222')->assertSee('coach@example.test');

        Livewire::test(ListTrainers::class)->searchTable('0861112222')->assertCanSeeTableRecords([$trainer]);

        Livewire::test(EditTrainer::class, ['record' => $trainer->getRouteKey()])
            ->fillForm(['user' => ['phone' => '0863334444']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0863334444', $trainer->user->fresh()->phone);
    }
}
