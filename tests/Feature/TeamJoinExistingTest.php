<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Livewire\TeamJoin;
use App\Livewire\Trainer\TeamRoster;
use App\Models\TeamMember;
use App\Notifications\AccountNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** สมาชิกที่สมัครเองทั่วไป เข้าทีมของเทรนเนอร์ได้ 3 ทาง โดยไม่ต้องสมัครใหม่ */
class TeamJoinExistingTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    #[Test]
    public function a_trainer_adds_an_existing_member_by_phone_from_the_team_page(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $member = $this->makeMember($gym);
        $member->user->update(['phone' => '0812345678']);

        Livewire::actingAs($trainer->user)->test(TeamRoster::class)
            ->call('openAddForm')
            ->set('lookupQuery', '081-234-5678')
            ->call('findExistingMember')
            ->assertHasNoErrors()
            ->assertSet('lookupMemberId', $member->id)
            ->call('addExistingMember')
            ->assertHasNoErrors()
            ->assertSet('showAddForm', false);

        $this->assertTrue($trainer->teamMembers()->whereKey($member->id)->exists());
        $this->assertSame($trainer->id, $member->fresh()->primary_trainer_id);
        $this->assertSame('manual', TeamMember::where('member_id', $member->id)->sole()->joined_via);
        Notification::assertSentTo($member->user, AccountNotice::class, fn (AccountNotice $n) => str_contains($n->title, 'เพิ่มเข้าทีม'));
    }

    #[Test]
    public function the_lookup_refuses_unknown_pending_other_branch_and_already_joined_members(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);

        $pending = $this->makeMember($gym, null, ['status' => MemberStatus::Pending, 'approved_at' => null]);
        $pending->user->update(['phone' => '0811111111']);

        $other = $this->makeMember($this->makeGym(['code' => 'B2', 'name' => 'สาขาสอง']));
        $other->user->update(['phone' => '0822222222']);

        $joined = $this->makeMember($gym, $trainer);
        $joined->user->update(['phone' => '0833333333']);

        $page = Livewire::actingAs($trainer->user)->test(TeamRoster::class)->call('openAddForm');

        foreach (['0899999999', '0811111111', '0822222222'] as $phone) {
            $page->set('lookupQuery', $phone)->call('findExistingMember')->assertHasErrors(['lookupQuery'])->assertSet('lookupMemberId', null);
        }

        $page->set('lookupQuery', '0833333333')->call('findExistingMember')->assertHasNoErrors()
            ->call('addExistingMember')->assertHasErrors(['lookupQuery']);

        $this->assertSame(1, TeamMember::where('member_id', $joined->id)->count());
    }

    #[Test]
    public function a_logged_in_member_opening_the_invite_link_joins_with_the_same_account(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym, ['invite_token' => 'invite-token-123']);
        $member = $this->makeMember($gym);

        $this->actingAs($member->user)->get('/team/join/invite-token-123')
            ->assertOk()
            ->assertSee('ไม่ต้องสมัครใหม่')
            ->assertSee('เข้าร่วมทีมของ')
            ->assertDontSee('สมัครเข้าทีม');

        Livewire::actingAs($member->user)->test(TeamJoin::class, ['token' => 'invite-token-123'])
            ->call('joinExisting')
            ->assertHasNoErrors()
            ->assertRedirect(route('member.reservations'));

        $this->assertTrue($trainer->teamMembers()->whereKey($member->id)->exists());
        $this->assertSame('invite_link', TeamMember::where('member_id', $member->id)->sole()->joined_via);
        Notification::assertSentTo($trainer->user, AccountNotice::class, fn (AccountNotice $n) => $n->title === 'ลูกทีมใหม่เข้าทีมของคุณ');

        // เปิดซ้ำ บอกว่าอยู่ในทีมแล้ว และสมัครใหม่ด้วยบัญชีที่ล็อกอินอยู่ไม่ได้
        $this->actingAs($member->user)->get('/team/join/invite-token-123')->assertOk()->assertSee('อยู่ในทีมของ');
        Livewire::actingAs($member->user)->test(TeamJoin::class, ['token' => 'invite-token-123'])->call('join')->assertForbidden();
    }

    #[Test]
    public function a_pending_member_who_joins_through_the_trainer_link_is_approved(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym, ['invite_token' => 'invite-token-123']);
        $member = $this->makeMember($gym, null, ['status' => MemberStatus::Pending, 'approved_at' => null]);

        Livewire::actingAs($member->user)->test(TeamJoin::class, ['token' => 'invite-token-123'])
            ->call('joinExisting')
            ->assertHasNoErrors();

        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
        $this->assertNotNull($member->fresh()->approved_at);
        $this->assertTrue($trainer->teamMembers()->whereKey($member->id)->exists());
    }

    #[Test]
    public function trainers_and_staff_cannot_join_a_team_and_full_teams_say_so(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym, ['invite_token' => 'invite-token-123']);
        $another = $this->makeAvailableTrainer($gym);

        $this->actingAs($another->user)->get('/team/join/invite-token-123')->assertOk()->assertSee('เข้าร่วมทีมไม่ได้');
        Livewire::actingAs($another->user)->test(TeamJoin::class, ['token' => 'invite-token-123'])->call('joinExisting')->assertForbidden();

        $trainer->update(['max_team_size' => 1]);
        $this->makeMember($gym, $trainer);
        $member = $this->makeMember($gym);

        $this->actingAs($member->user)->get('/team/join/invite-token-123')->assertOk()->assertSee('ทีมเต็มแล้ว');
        Livewire::actingAs($member->user)->test(TeamJoin::class, ['token' => 'invite-token-123'])->call('joinExisting')->assertHasErrors(['accept']);
        $this->assertFalse($trainer->teamMembers()->whereKey($member->id)->exists());
    }

    #[Test]
    public function setting_a_primary_trainer_in_the_admin_form_puts_the_member_in_that_team(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $member = $this->makeMember($gym);
        $member->user->update(['phone' => '0812345678']);
        $this->actingAs($this->makeAdmin($gym));

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm(['primary_trainer_id' => $trainer->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($trainer->teamMembers()->whereKey($member->id)->exists());
        $this->assertSame('admin', TeamMember::where('member_id', $member->id)->sole()->joined_via);
    }

    #[Test]
    public function guests_still_see_the_registration_form_and_come_back_after_logging_in(): void
    {
        $gym = $this->makeGym();
        $this->makeAvailableTrainer($gym, ['invite_token' => 'invite-token-123']);

        $this->get('/team/join/invite-token-123')
            ->assertOk()
            ->assertSee('สมัครเข้าทีม')
            ->assertSee('มีบัญชีอยู่แล้ว')
            ->assertSessionHas('url.intended', url('/team/join/invite-token-123'));
    }
}
