<?php

namespace App\Services;

use App\Exceptions\MemberGroupException;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Trainer;
use App\Support\GroupPalette;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * กติกาของกลุ่มลูกทีมอยู่ที่นี่ที่เดียว
 *
 * เทรนเนอร์เป็นคนสร้างกลุ่มและจัดสมาชิกเอง แต่เพดานจำนวนคนเป็นของแอดมิน
 * (branches.max_group_size และค่าตั้งทับรายกลุ่มที่ member_groups.max_members)
 * ดังนั้นห้ามให้เทรนเนอร์แก้ max_members ผ่านทางนี้
 */
class MemberGroupService
{
    public function createGroup(Trainer $trainer, string $name, ?string $description = null, ?string $color = null): MemberGroup
    {
        $name = trim($name);

        try {
            return MemberGroup::create([
                'trainer_id' => $trainer->id,
                'branch_id' => $trainer->branch_id,
                'name' => $name,
                'description' => $description ?: null,
                'color' => $this->safeColor($color),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw MemberGroupException::duplicateName($name);
            }

            throw $e;
        }
    }

    public function rename(MemberGroup $group, Trainer $trainer, string $name, ?string $description = null, ?string $color = null): MemberGroup
    {
        $this->assertOwner($group, $trainer);

        $name = trim($name);

        try {
            $group->update([
                'name' => $name,
                'description' => $description ?: null,
                'color' => $this->safeColor($color ?? $group->color),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw MemberGroupException::duplicateName($name);
            }

            throw $e;
        }

        return $group->fresh();
    }

    public function deleteGroup(MemberGroup $group, Trainer $trainer): void
    {
        $this->assertOwner($group, $trainer);

        // ลบแค่กลุ่ม ลูกทีมยังอยู่ในทีมตามเดิม
        $group->members()->detach();
        $group->delete();
    }

    public function addMember(MemberGroup $group, Member $member, Trainer $trainer): void
    {
        $this->assertOwner($group, $trainer);

        DB::transaction(function () use ($group, $member, $trainer) {
            // ล็อกกลุ่มก่อนนับจำนวน ไม่งั้นการเพิ่มพร้อมกันจะทะลุเพดานได้
            $group = MemberGroup::whereKey($group->getKey())->lockForUpdate()->firstOrFail();

            if ($member->branch_id !== $group->branch_id) {
                throw MemberGroupException::branchMismatch();
            }

            $inTeam = $trainer->teamMemberships()
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->exists();

            if (! $inTeam) {
                throw MemberGroupException::notInTeam();
            }

            if ($group->members()->whereKey($member->id)->exists()) {
                throw MemberGroupException::alreadyInGroup();
            }

            $capacity = $group->capacity();

            if ($group->members()->count() >= $capacity) {
                throw MemberGroupException::groupFull($capacity);
            }

            $group->members()->attach($member->id, ['joined_at' => now()]);
        });
    }

    public function removeMember(MemberGroup $group, Member $member, Trainer $trainer): void
    {
        $this->assertOwner($group, $trainer);

        $group->members()->detach($member->id);
    }

    /** รับเฉพาะสีที่มีอยู่จริงในชุด ไม่งั้นถอยไปใช้สีเริ่มต้น */
    protected function safeColor(?string $color): string
    {
        return in_array($color, GroupPalette::keys(), true) ? $color : GroupPalette::DEFAULT;
    }

    protected function assertOwner(MemberGroup $group, Trainer $trainer): void
    {
        if ($group->trainer_id !== $trainer->id) {
            throw MemberGroupException::notOwner();
        }
    }
}
