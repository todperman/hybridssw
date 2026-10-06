<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Enums\UserRole;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Livewire\Member\MyBookings;
use App\Livewire\Member\Schedule;
use App\Livewire\Trainer\BookingBoard;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

/**
 * หน้าเว็บทุกหน้าที่แสดงการจองเคยถือว่ามีเทรนเนอร์เสมอ
 * การจองเองไม่มีเทรนเนอร์ จึงต้องเรนเดอร์ทุกหน้าจริงกับข้อมูลแบบนั้น
 */
class SelfBookingPagesTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ตรึงเวลาตอนเช้า รอบตอนเย็นวันเดียวกันจึงยังไม่เริ่มเสมอ
        $this->travelTo(now()->setTime(8, 0));

        // หน้าที่ทดสอบในไฟล์นี้ต้องมีคำขอรออนุมัติให้แสดง จึงเปิดโหมดอนุมัติการจองเองไว้
        config(['gym.booking.approval' => 'self']);
    }

    protected function admin($branch): User
    {
        return User::create([
            'branch_id' => $branch->id, 'first_name' => 'แอด', 'last_name' => 'มิน',
            'email' => 'admin@example.test', 'password' => 'password', 'role' => UserRole::Admin,
        ])->refresh();
    }

    // --- หน้าจองรอบของสมาชิก ---

    #[Test]
    public function a_member_can_request_a_seat_from_the_schedule_page(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 5]);
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $member = $this->makeMember($branch);

        Livewire::actingAs($member->user)
            ->test(Schedule::class)
            ->assertSee('18:00')
            ->assertSee('ว่าง 5 จาก 5 ที่')
            ->call('book', $session->id)
            ->assertDispatched('toast', tone: 'success', title: 'ส่งคำขอจองแล้ว')
            ->assertSee('รออนุมัติ');

        $this->assertSame(BookingStatus::Pending, Booking::firstOrFail()->status);
    }

    #[Test]
    public function full_and_exclusive_sessions_cannot_be_booked_from_the_page(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1]);
        $full = $this->makeSession($branch, ['starts_at' => now()->setTime(17, 0)]);
        $this->makeSession($branch, ['starts_at' => now()->setTime(19, 0), 'mode' => SessionMode::Exclusive]);

        app(BookingService::class)->bookSelf($full, $this->makeMember($branch));

        Livewire::actingAs($this->makeMember($branch)->user)
            ->test(Schedule::class)
            ->assertSee('17:00')
            ->assertSee('เต็มแล้ว')
            ->assertDontSee('19:00');
    }

    #[Test]
    public function a_member_cannot_book_a_session_of_another_branch(): void
    {
        $mine = $this->makeBranch();
        $other = $this->makeBranch();
        $session = $this->makeSession($other, ['starts_at' => now()->setTime(18, 0)]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->makeMember($mine)->user)
            ->test(Schedule::class)
            ->call('book', $session->id);
    }

    /**
     * Livewire::test() เรนเดอร์แค่ตัวคอมโพเนนต์ ไม่ผ่าน layout ของหน้า
     * เคยลืมใส่ layout แล้วหน้าพัง 500 ทั้งที่เทสต์คอมโพเนนต์ผ่านหมด จึงต้องเปิดเป็นหน้าจริงด้วย
     */
    #[Test]
    public function every_member_page_opens_as_a_full_page(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $member = $this->makeMember($branch);
        app(BookingService::class)->bookSelf($session, $member);

        $this->actingAs($member->user);

        $this->get(route('member.schedule'))->assertOk()->assertSee('เลือกรอบที่สะดวก')->assertSee('จองรอบ');
        $this->get(route('member.bookings'))->assertOk()->assertSee('รออนุมัติ');
    }

    #[Test]
    public function only_members_can_open_the_schedule_page(): void
    {
        $trainer = $this->makeTrainer($this->makeBranch());

        $this->actingAs($trainer->user)->get(route('member.schedule'))->assertForbidden();
    }

    // --- หน้าคิวของฉัน ---

    #[Test]
    public function my_bookings_shows_a_trainerless_pending_request_and_lets_it_be_withdrawn(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $member = $this->makeMember($branch);
        $booking = app(BookingService::class)->bookSelf($session, $member);

        Livewire::actingAs($member->user)
            ->test(MyBookings::class)
            ->assertSee('รออนุมัติ')
            ->assertSee('จองด้วยตัวเอง')
            ->assertSee('ถอนคำขอ')
            ->assertDontSee('เครดิตคงเหลือ')
            ->call('cancel', $booking->id)
            ->assertDispatched('toast', title: 'ถอนคำขอแล้ว');

        $this->assertSame(0, $session->fresh()->booked_count);
    }

    #[Test]
    public function rejected_self_bookings_render_in_history(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $member = $this->makeMember($branch);
        $booking = app(BookingService::class)->bookSelf($session, $member);
        app(BookingService::class)->reject($booking);

        $session->update(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);

        Livewire::actingAs($member->user)
            ->test(MyBookings::class)
            ->assertSee('จองด้วยตัวเอง')
            ->assertSee('ยกเลิก');
    }

    // --- หน้าตารางของเทรนเนอร์ ---

    #[Test]
    public function a_trainer_sees_self_bookings_as_anonymous_taken_seats(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 5]);
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $trainer = $this->makeTrainer($branch);

        $stranger = $this->makeMember($branch);
        $stranger->user->update(['first_name' => 'คนนอก', 'last_name' => 'จองเอง']);
        app(BookingService::class)->bookSelf($session, $stranger);

        Livewire::actingAs($trainer->user)
            ->test(BookingBoard::class)
            ->assertSee('ผู้เล่นอื่น 1')
            ->assertSee('ว่าง 4/5')
            ->assertDontSee('คนนอก จองเอง');
    }

    // --- หลังบ้าน ---

    #[Test]
    public function the_admin_sees_pending_requests_first_and_can_approve_them(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $booking = app(BookingService::class)->bookSelf($session, $this->makeMember($branch));
        $admin = $this->admin($branch);

        $this->actingAs($admin);

        Livewire::test(ListBookings::class)
            ->assertSet('activeTab', 'pending')
            ->assertCanSeeTableRecords([$booking])
            ->callTableAction('approve', $booking);

        $booking->refresh();
        $this->assertSame(BookingStatus::Booked, $booking->status);
        $this->assertSame($admin->id, $booking->approved_by_user_id);
    }

    #[Test]
    public function the_admin_can_reject_with_a_reason(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $booking = app(BookingService::class)->bookSelf($session, $this->makeMember($branch));

        $this->actingAs($this->admin($branch));

        Livewire::test(ListBookings::class)
            ->callTableAction('reject', $booking, data: ['reason' => 'ยอดโอนไม่ตรง']);

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('ยอดโอนไม่ตรง', $booking->cancellation_reason);
        $this->assertSame(0, $session->fresh()->booked_count);
    }

    #[Test]
    public function the_bookings_menu_shows_how_many_requests_are_waiting(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);

        $this->assertNull(\App\Filament\Resources\Bookings\BookingResource::getNavigationBadge());

        app(BookingService::class)->bookSelf($session, $this->makeMember($branch));
        app(BookingService::class)->bookSelf($session, $this->makeMember($branch));

        $this->assertSame('2', \App\Filament\Resources\Bookings\BookingResource::getNavigationBadge());
    }

    #[Test]
    public function the_admin_bookings_page_renders_with_self_bookings_in_it(): void
    {
        $branch = $this->makeBranch();
        $session = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        app(BookingService::class)->bookSelf($session, $this->makeMember($branch));

        $this->actingAs($this->admin($branch))
            ->get('/admin/bookings')
            ->assertSuccessful()
            ->assertSee('จองเอง');
    }
}
