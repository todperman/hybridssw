<?php

namespace Tests\Feature;

use App\Exceptions\MemberGroupException;
use App\Models\MemberGroup;
use App\Services\MemberGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class MemberGroupTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected MemberGroupService $groups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groups = app(MemberGroupService::class);
    }

    #[Test]
    public function a_group_uses_the_cap_the_admin_set_on_the_branch(): void
    {
        $branch = $this->makeBranch(['max_group_size' => 4]);
        $trainer = $this->makeTrainer($branch);

        $group = $this->groups->createGroup($trainer, 'กลุ่มเช้า');

        $this->assertSame(4, $group->capacity());
        $this->assertSame(4, $group->seatsRemaining());
    }

    #[Test]
    public function an_admin_override_on_the_group_beats_the_branch_cap(): void
    {
        $branch = $this->makeBranch(['max_group_size' => 4]);
        $trainer = $this->makeTrainer($branch);

        $group = $this->groups->createGroup($trainer, 'กลุ่มพิเศษ');
        $group->update(['max_members' => 8]);

        $this->assertSame(8, $group->fresh()->capacity());
    }

    #[Test]
    public function members_cannot_be_added_beyond_the_cap(): void
    {
        $branch = $this->makeBranch(['max_group_size' => 2]);
        $trainer = $this->makeTrainer($branch);
        $group = $this->groups->createGroup($trainer, 'กลุ่มเล็ก');

        $this->groups->addMember($group, $this->makeMember($branch, $trainer), $trainer);
        $this->groups->addMember($group, $this->makeMember($branch, $trainer), $trainer);

        $this->assertTrue($group->fresh()->isFull());

        $this->expectException(MemberGroupException::class);
        $this->expectExceptionMessage('รับได้สูงสุด 2 คน');

        $this->groups->addMember($group, $this->makeMember($branch, $trainer), $trainer);
    }

    #[Test]
    public function only_members_of_the_trainers_own_team_can_join(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $group = $this->groups->createGroup($trainer, 'กลุ่มของฉัน');

        $stranger = $this->makeMember($branch, null);

        $this->expectExceptionMessage('ไม่ได้อยู่ในทีมของคุณ');

        $this->groups->addMember($group, $stranger, $trainer);
    }

    #[Test]
    public function a_trainer_cannot_touch_another_trainers_group(): void
    {
        $branch = $this->makeBranch();
        $owner = $this->makeTrainer($branch);
        $other = $this->makeTrainer($branch);

        $group = $this->groups->createGroup($owner, 'กลุ่มของเจ้าของ');
        $member = $this->makeMember($branch, $other);

        $this->expectExceptionMessage('ไม่ใช่ของคุณ');

        $this->groups->addMember($group, $member, $other);
    }

    #[Test]
    public function the_same_member_cannot_be_added_twice(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $group = $this->groups->createGroup($trainer, 'กลุ่มซ้ำ');
        $member = $this->makeMember($branch, $trainer);

        $this->groups->addMember($group, $member, $trainer);

        $this->expectExceptionMessage('อยู่ในกลุ่มนี้แล้ว');

        $this->groups->addMember($group, $member, $trainer);
    }

    #[Test]
    public function a_trainer_cannot_have_two_groups_with_the_same_name(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);

        $this->groups->createGroup($trainer, 'กลุ่มเช้า');

        $this->expectExceptionMessage('มีกลุ่มชื่อ "กลุ่มเช้า" อยู่แล้ว');

        $this->groups->createGroup($trainer, 'กลุ่มเช้า');
    }

    #[Test]
    public function a_member_may_belong_to_several_groups(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);

        $morning = $this->groups->createGroup($trainer, 'กลุ่มเช้า');
        $beginner = $this->groups->createGroup($trainer, 'กลุ่มมือใหม่');

        $this->groups->addMember($morning, $member, $trainer);
        $this->groups->addMember($beginner, $member, $trainer);

        $this->assertSame(2, $member->fresh()->groups()->count());
    }

    #[Test]
    public function deleting_a_group_keeps_the_members_in_the_team(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $member = $this->makeMember($branch, $trainer);

        $group = $this->groups->createGroup($trainer, 'กลุ่มที่จะลบ');
        $this->groups->addMember($group, $member, $trainer);

        $this->groups->deleteGroup($group, $trainer);

        $this->assertSame(0, MemberGroup::count());
        $this->assertSame(1, $trainer->fresh()->teamMembers()->count(), 'ลูกทีมต้องยังอยู่ในทีม');
    }
}
