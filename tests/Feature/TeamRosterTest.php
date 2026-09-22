<?php

namespace Tests\Feature;

use App\Livewire\Trainer\TeamRoster;
use App\Models\Member;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class TeamRosterTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    #[Test]
    public function a_trainer_can_add_a_member_without_sending_an_invite(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);

        Livewire::actingAs($trainer->user)
            ->test(TeamRoster::class)
            ->set('newFirstName', 'สมชาย')
            ->set('newLastName', 'ใจดี')
            ->set('newNickname', 'ชาย')
            ->set('newEmail', 'somchai@example.com')
            ->call('addMember')
            ->assertHasNoErrors();

        $user = User::where('email', 'somchai@example.com')->firstOrFail();

        $this->assertSame('ชาย', $user->nickname);
        $this->assertSame('สมชาย ใจดี', $user->name);

        $member = Member::where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('team_members', [
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function adding_a_member_is_blocked_when_the_team_is_full(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['max_team_size' => 1]);

        $this->makeMember($branch, $trainer);

        Livewire::actingAs($trainer->user)
            ->test(TeamRoster::class)
            ->set('newFirstName', 'เกิน')
            ->set('newLastName', 'โควตา')
            ->set('newEmail', 'over@example.com')
            ->call('addMember')
            ->assertHasErrors('newEmail');

        $this->assertDatabaseMissing('users', ['email' => 'over@example.com']);
    }

    #[Test]
    public function a_trainer_can_edit_their_own_members_details(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);

        Livewire::actingAs($trainer->user)
            ->test(TeamRoster::class)
            ->call('editMember', $member->id)
            ->set('editNickname', 'บอล')
            ->set('editPhone', '0812345678')
            ->set('editGender', 'male')
            ->set('editEmergencyName', 'สมศรี')
            ->call('updateMember')
            ->assertHasNoErrors();

        $member->refresh();

        $this->assertSame('บอล', $member->user->nickname);
        $this->assertSame('0812345678', $member->user->phone);
        $this->assertSame('สมศรี', $member->emergency_contact_name);
    }

    #[Test]
    public function a_trainer_cannot_edit_someone_elses_member(): void
    {
        $branch = $this->makeBranch();
        $mine = $this->makeTrainer($branch);
        $theirs = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $theirs);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($mine->user)
            ->test(TeamRoster::class)
            ->call('editMember', $member->id);
    }

    #[Test]
    public function a_trainer_note_is_private_to_that_trainers_team(): void
    {
        $branch = $this->makeBranch();
        $one = $this->makeTrainer($branch);
        $two = $this->makeTrainer($branch);
        // makeMember ผูกกับเทรนเนอร์คนแรกให้แล้ว เพิ่มเฉพาะทีมที่สอง
        $member = $this->makeMember($branch, $one);

        TeamMember::create([
            'trainer_id' => $two->id,
            'member_id' => $member->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Livewire::actingAs($one->user)
            ->test(TeamRoster::class)
            ->call('saveNote', $member->id, 'เข่าขวาเคยบาดเจ็บ');

        $this->assertDatabaseHas('team_members', [
            'trainer_id' => $one->id,
            'member_id' => $member->id,
            'trainer_note' => 'เข่าขวาเคยบาดเจ็บ',
        ]);

        // โน้ตของเทรนเนอร์คนแรกต้องไม่รั่วไปทีมของอีกคน
        $this->assertDatabaseHas('team_members', [
            'trainer_id' => $two->id,
            'member_id' => $member->id,
            'trainer_note' => null,
        ]);
    }
}
