<?php

namespace Tests\Support;

use App\Enums\MemberStatus;
use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\ScheduleTemplate;
use App\Models\TeamMember;
use App\Models\Trainer;
use App\Models\User;

/** ตัวช่วยประกอบข้อมูลตั้งต้นให้เทสต์อ่านง่าย */
trait BuildsGym
{
    protected function makeBranch(array $attributes = []): Branch
    {
        return Branch::create(array_merge([
            'code' => 'B'.fake()->unique()->numerify('####'),
            'name' => 'สาขาทดสอบ',
        ], $attributes));
    }

    protected function makeTrainer(Branch $branch, array $attributes = []): Trainer
    {
        $user = User::create([
            'branch_id' => $branch->id,
            'first_name' => 'เทรนเนอร์',
            'last_name' => 'ทดสอบ',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Trainer,
        ]);

        return Trainer::create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => TrainerType::Internal,
            'status' => TrainerStatus::Approved,
            'approved_at' => now(),
        ], $attributes));
    }

    protected function makeMember(Branch $branch, ?Trainer $trainer = null, array $attributes = []): Member
    {
        $user = User::create([
            'branch_id' => $branch->id,
            'first_name' => 'ลูกทีม',
            'last_name' => 'ทดสอบ',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Member,
        ]);

        $member = Member::create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'primary_trainer_id' => $trainer?->id,
            'status' => MemberStatus::Active,
            'parq_signed_at' => now(),
        ], $attributes));

        if ($trainer) {
            TeamMember::create([
                'trainer_id' => $trainer->id,
                'member_id' => $member->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return $member;
    }

    protected function makeTemplate(Branch $branch, array $attributes = []): ScheduleTemplate
    {
        return ScheduleTemplate::create(array_merge([
            'branch_id' => $branch->id,
            'name' => 'กฎทดสอบ',
            'day_of_week' => 1,
            'start_time' => '18:00',
            'end_time' => '21:00',
        ], $attributes));
    }
}
