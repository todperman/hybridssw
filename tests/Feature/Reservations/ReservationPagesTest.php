<?php

namespace Tests\Feature\Reservations;

use App\Enums\RequestType;
use App\Enums\ReservationStatus;
use App\Livewire\NotificationBell;
use App\Livewire\Reservations\BookingWizard;
use App\Livewire\Reservations\ReservationDetail;
use App\Livewire\Reservations\ReservationList;
use App\Livewire\Trainer\AvailabilityPlanner;
use App\Models\Reservation;
use App\Notifications\ReservationNotice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** หน้าจอฝั่งผู้ใช้ของระบบจอง Private Gym */
class ReservationPagesTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));
    }

    #[Test]
    public function every_new_page_renders_inside_the_app_layout(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $member = $this->makeMember($gym);
        $r = $this->bookAsTrainee($gym, $member, [], $trainer);

        $this->actingAs($member->user);
        foreach (['/member/book', '/member/reservations', '/reservations/'.$r->reference] as $url) {
            $this->get($url)->assertOk()->assertSee('data-app-nav', false);
        }

        $this->actingAs($trainer->user);
        foreach (['/trainer/book', '/trainer/jobs', '/trainer/availability', '/reservations/'.$r->reference] as $url) {
            $this->get($url)->assertOk()->assertSee('data-app-nav', false);
        }
    }

    #[Test]
    public function the_dashboard_sends_each_role_to_its_new_home(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $member = $this->makeMember($gym);

        $this->actingAs($trainer->user)->get('/dashboard')->assertRedirect(route('trainer.jobs'));
        $this->actingAs($member->user)->get('/dashboard')->assertRedirect(route('member.reservations'));
    }

    #[Test]
    public function a_trainee_books_with_a_friend_and_lands_on_the_payment_page(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $friend = $this->makeMember($gym);
        $friend->user->update(['phone' => '0812345678']);

        Livewire::actingAs($me->user)
            ->test(BookingWizard::class)
            ->assertSet('mode', 'trainee')
            ->assertSet('memberIds', [$me->id])
            ->call('selectDay', '2026-10-08')
            ->call('setHours', 2)
            ->call('pickStart', '18:00')
            ->assertSee($trainer->user->displayName())
            ->set('lookup', '081-234-5678')
            ->call('addMember')
            ->assertSet('memberIds', [$me->id, $friend->id])
            ->call('chooseTrainer', $trainer->id)
            ->call('choosePayer', $friend->id)
            ->call('submit')
            ->assertRedirect();

        $r = Reservation::sole();
        $this->assertSame(2, $r->hours);
        $this->assertSame('2026-10-08 18:00', $r->starts_at->format('Y-m-d H:i'));
        $this->assertSame($friend->id, $r->payer_member_id);
        $this->assertSame($trainer->id, $r->trainer_id);
        $this->assertSame('1600.00', (string) $r->amount);
    }

    #[Test]
    public function lookup_needs_an_exact_match_and_never_lists_members(): void
    {
        $gym = $this->makeGym();
        $me = $this->makeMember($gym);
        $this->makeMember($gym)->user->update(['phone' => '0812345678']);

        Livewire::actingAs($me->user)
            ->test(BookingWizard::class)
            ->set('lookup', '0812')
            ->call('addMember')
            ->assertSet('memberIds', [$me->id])
            ->assertSet('lookupError', fn ($e) => $e !== null);
    }

    #[Test]
    public function a_trainee_cannot_remove_themselves_from_the_group(): void
    {
        $gym = $this->makeGym();
        $me = $this->makeMember($gym);

        Livewire::actingAs($me->user)
            ->test(BookingWizard::class)
            ->call('removeMember', $me->id)
            ->assertSet('memberIds', [$me->id]);
    }

    #[Test]
    public function the_no_trainer_option_only_appears_for_privileged_groups(): void
    {
        $gym = $this->makeGym();
        $plain = $this->makeMember($gym);
        $privileged = $this->makeMember($gym, attributes: ['can_book_without_trainer' => true]);

        Livewire::actingAs($plain->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-08')->call('pickStart', '18:00')
            ->assertDontSee('เข้าใช้โดยไม่มี Trainer');

        Livewire::actingAs($privileged->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-08')->call('pickStart', '18:00')
            ->assertSee('เข้าใช้โดยไม่มี Trainer')
            ->call('chooseTrainer', null)
            ->call('submit')
            ->assertRedirect();

        $this->assertNull(Reservation::sole()->trainer_id);
    }

    #[Test]
    public function a_trainer_books_for_team_members_and_is_the_assigned_trainer(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $a = $this->makeMember($gym, $trainer);
        $b = $this->makeMember($gym, $trainer);

        Livewire::actingAs($trainer->user)
            ->test(BookingWizard::class)
            ->assertSet('mode', 'trainer')
            ->assertSet('memberIds', [])
            ->call('selectDay', '2026-10-08')
            ->call('pickStart', '10:00')
            ->call('addFromTeam', $a->id)
            ->call('addFromTeam', $b->id)
            ->call('choosePayer', $b->id)
            ->call('submit')
            ->assertRedirect();

        $r = Reservation::sole();
        $this->assertSame($trainer->id, $r->trainer_id);
        $this->assertSame($b->id, $r->payer_member_id);
        $this->assertSame(Reservation::VIA_TRAINER, $r->created_via);
    }

    #[Test]
    public function a_trainer_only_sees_start_times_they_are_free_for(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeTrainer($gym);
        $trainer->availabilities()->create(['day_of_week' => 4, 'start_time' => '10:00', 'end_time' => '12:00']);

        $times = Livewire::actingAs($trainer->user)
            ->test(BookingWizard::class)
            ->call('selectDay', '2026-10-08') // พฤหัสบดี
            ->instance()->startTimes;

        $this->assertSame(['10:00', '11:00'], array_map(fn ($t) => $t->format('H:i'), $times));
    }

    #[Test]
    public function a_failed_submit_shows_the_reason_instead_of_crashing(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $other = $this->makeMember($gym);

        $page = Livewire::actingAs($me->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-08')->call('pickStart', '18:00')
            ->call('chooseTrainer', $trainer->id);

        // มีคนจองตัดหน้าระหว่างที่หน้ายังเปิดอยู่
        $this->bookAsTrainee($gym, $other, [], $trainer, CarbonImmutable::parse('2026-10-08 18:00'));

        $page->call('submit')->assertNoRedirect()->assertSet('error', fn ($e) => filled($e));
        $this->assertSame(1, Reservation::count());
    }

    #[Test]
    public function strangers_cannot_open_someone_elses_reservation(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $r = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer);
        $stranger = $this->makeMember($gym);

        $this->actingAs($stranger->user)->get('/reservations/'.$r->reference)->assertNotFound();
        $this->actingAs($this->makeAvailableTrainer($gym)->user)->get('/reservations/'.$r->reference)->assertNotFound();
    }

    #[Test]
    public function the_payment_page_shows_manual_instructions_and_expires_when_time_runs_out(): void
    {
        $gym = $this->makeGym(['payment_instructions' => 'โอนเข้าบัญชีกสิกร 123-4-56789-0']);
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $r = $this->bookAsTrainee($gym, $me, [], $trainer);

        $page = Livewire::actingAs($me->user)
            ->test(ReservationDetail::class, ['reference' => $r->reference])
            ->assertSee('โอนเข้าบัญชีกสิกร')
            ->assertSee($r->reference);

        $this->travel(31)->minutes();
        $page->call('refreshStatus');

        $this->assertSame(ReservationStatus::Expired, $r->refresh()->status);
    }

    #[Test]
    public function the_payer_reschedules_directly_before_the_deadline(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $me, [], $trainer));

        Livewire::actingAs($me->user)
            ->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('openReschedule')
            ->call('pickRescheduleDay', '2026-10-09')
            ->call('pickRescheduleStart', '07:00')
            ->call('submitReschedule')
            ->assertSet('error', null);

        $this->assertSame('2026-10-09 07:00', $r->refresh()->starts_at->format('Y-m-d H:i'));
    }

    #[Test]
    public function after_the_deadline_the_page_sends_a_request_and_requires_a_reason(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $me, [], $trainer));

        $this->travelTo(CarbonImmutable::parse('2026-10-08 08:00'));

        $page = Livewire::actingAs($me->user)
            ->test(ReservationDetail::class, ['reference' => $r->reference])
            ->call('openReschedule')
            ->call('pickRescheduleDay', '2026-10-09')
            ->call('pickRescheduleStart', '07:00')
            ->call('submitReschedule')
            ->assertSet('error', fn ($e) => str_contains($e, 'เหตุผล'));

        $page->set('rescheduleReason', 'ติดประชุม')->call('submitReschedule');

        $this->assertSame('2026-10-08 18:00', $r->refresh()->starts_at->format('Y-m-d H:i'));
        $this->assertSame(RequestType::Reschedule, $r->requests()->sole()->type);
    }

    #[Test]
    public function the_assigned_trainer_can_ask_to_withdraw(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $r = $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer));

        Livewire::actingAs($trainer->user)
            ->test(ReservationDetail::class, ['reference' => $r->reference])
            ->set('withdrawing', true)
            ->set('withdrawReason', 'ป่วย')
            ->call('submitWithdrawal')
            ->assertHasNoErrors();

        $this->assertSame(RequestType::TrainerWithdrawal, $r->requests()->sole()->type);
    }

    #[Test]
    public function lists_show_upcoming_and_past_reservations_for_the_right_people(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym);
        $friend = $this->makeMember($gym);
        $r = $this->bookAsTrainee($gym, $me, [$friend], $trainer);

        Livewire::actingAs($friend->user)->test(ReservationList::class)
            ->assertSee($r->timeLabel());

        Livewire::actingAs($trainer->user)->test(ReservationList::class)
            ->assertSee($r->timeLabel());

        Livewire::actingAs($this->makeMember($gym)->user)->test(ReservationList::class)
            ->assertDontSee($r->timeLabel());

        $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00'));
        Livewire::actingAs($me->user)->test(ReservationList::class)
            ->assertDontSee($r->timeLabel())
            ->call('setTab', 'past')
            ->assertSee($r->timeLabel());
    }

    #[Test]
    public function a_trainer_cannot_remove_hours_that_a_booking_depends_on(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $this->confirmManually($this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer));
        $thursday = $trainer->availabilities()->where('day_of_week', 4)->sole();

        Livewire::actingAs($trainer->user)->test(AvailabilityPlanner::class)
            ->call('removeWindow', $thursday->id)
            ->assertSet('error', fn ($e) => filled($e));

        $this->assertModelExists($thursday);
    }

    #[Test]
    public function a_trainer_adds_weekly_hours_and_time_off(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeTrainer($gym);

        Livewire::actingAs($trainer->user)->test(AvailabilityPlanner::class)
            ->set('weekdays', [1, 3])
            ->set('weeklyStart', '08:00')
            ->set('weeklyEnd', '12:00')
            ->call('addWeekly')
            ->set('offDate', '2026-10-12')
            ->call('addTimeOff')
            ->call('toggleAccepting')
            ->assertSet('error', null);

        $this->assertSame([1, 3], $trainer->availabilities()->orderBy('day_of_week')->pluck('day_of_week')->all());
        $this->assertTrue($trainer->timeOffs()->sole()->isWholeDay());
        $this->assertFalse($trainer->refresh()->accepts_bookings);
    }

    #[Test]
    public function the_bell_counts_unread_and_opens_the_linked_page(): void
    {
        $gym = $this->makeGym();
        $me = $this->makeMember($gym);
        $me->user->notify(new ReservationNotice('ทดสอบ', 'เนื้อความ', url('/reservations/HS1'), 'HS1'));
        $id = $me->user->notifications()->sole()->id;

        Livewire::actingAs($me->user)->test(NotificationBell::class)
            ->assertSee('ทดสอบ')
            ->call('open', $id)
            ->assertRedirect(url('/reservations/HS1'));

        $this->assertNotNull($me->user->notifications()->sole()->read_at);
    }

    #[Test]
    public function phones_typed_with_dashes_are_stored_as_digits_so_lookup_finds_them(): void
    {
        $gym = $this->makeGym();
        $me = $this->makeMember($gym);
        $friend = $this->makeMember($gym);
        $friend->user->update(['phone' => '081-234-5678']);

        $this->assertSame('0812345678', $friend->user->refresh()->phone);

        Livewire::actingAs($me->user)->test(BookingWizard::class)
            ->set('lookup', '081 234 5678')
            ->call('addMember')
            ->assertSet('memberIds', [$me->id, $friend->id]);
    }

    #[Test]
    public function a_trainer_adds_a_whole_group_up_to_the_limit(): void
    {
        $gym = $this->makeGym(['max_trainees' => 3]);
        $trainer = $this->makeAvailableTrainer($gym);
        $members = collect(range(1, 4))->map(fn () => $this->makeMember($gym, $trainer));
        $group = \App\Models\MemberGroup::create(['trainer_id' => $trainer->id, 'branch_id' => $gym->id, 'name' => 'กลุ่มเช้า']);
        $group->members()->attach($members->pluck('id'), ['joined_at' => now()]);

        Livewire::actingAs($trainer->user)->test(BookingWizard::class)
            ->assertSee('กลุ่มเช้า')
            ->call('addGroup', $group->id)
            ->assertSet('memberIds', $members->take(3)->pluck('id')->all())
            ->assertSet('lookupError', fn ($e) => str_contains((string) $e, 'เพิ่มได้ 3 คน'));
    }
}
