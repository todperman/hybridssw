<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberPackage;
use App\Models\Package;
use App\Models\ScheduleTemplate;
use App\Models\TeamMember;
use App\Models\Trainer;
use App\Models\User;
use App\Services\SessionGenerator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::create([
            'code' => 'SSW-MAIN',
            'name' => 'Srisawan Hybrid Workout — สาขาสำนักงานใหญ่',
            'phone' => '056-000000',
            'address' => 'นครสวรรค์',
            'default_capacity' => 5,
            'slot_duration_minutes' => 60,
        ]);

        $annex = Branch::create([
            'code' => 'SSW-ANNEX',
            'name' => 'Srisawan Hybrid Workout — สาขาย่อย',
            'phone' => '056-000001',
            'default_capacity' => 5,
            'slot_duration_minutes' => 60,
        ]);

        User::create([
            'first_name' => 'ผู้ดูแล',
            'last_name' => 'ระบบ',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
            'phone' => '0800000000',
            'email_verified_at' => now(),
        ]);

        User::create([
            'branch_id' => $main->id,
            'first_name' => 'เจ้าหน้าที่',
            'last_name' => 'หน้าเคาน์เตอร์',
            'email' => 'staff@example.com',
            'password' => 'password',
            'role' => UserRole::Staff,
            'email_verified_at' => now(),
        ]);

        // เวลาเปิดประจำสัปดาห์
        foreach ([1, 2, 3, 4, 5] as $day) {
            ScheduleTemplate::create([
                'branch_id' => $main->id,
                'name' => 'จันทร์-ศุกร์ เช้า',
                'day_of_week' => $day,
                'start_time' => '06:00',
                'end_time' => '10:00',
                'capacity' => 5,
            ]);

            ScheduleTemplate::create([
                'branch_id' => $main->id,
                'name' => 'จันทร์-ศุกร์ เย็น',
                'day_of_week' => $day,
                'start_time' => '17:00',
                'end_time' => '21:00',
                'capacity' => 5,
            ]);
        }

        foreach ([0, 6] as $day) {
            ScheduleTemplate::create([
                'branch_id' => $main->id,
                'name' => 'เสาร์-อาทิตย์',
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '18:00',
                'capacity' => 5,
            ]);
        }

        foreach ([1, 3, 5] as $day) {
            ScheduleTemplate::create([
                'branch_id' => $annex->id,
                'name' => 'สาขาย่อย เย็น',
                'day_of_week' => $day,
                'start_time' => '17:00',
                'end_time' => '20:00',
                'capacity' => 5,
            ]);
        }

        $packages = collect([
            ['name' => 'ทดลอง 4 ครั้ง', 'credits' => 4, 'price' => 800, 'validity_days' => 30],
            ['name' => 'แพ็ก 10 ครั้ง', 'credits' => 10, 'price' => 1800, 'validity_days' => 90],
            ['name' => 'แพ็ก 30 ครั้ง', 'credits' => 30, 'price' => 4500, 'validity_days' => 180],
        ])->map(fn ($p) => Package::create($p + ['branch_id' => $main->id]));

        // เทรนเนอร์ภายใน อนุมัติอัตโนมัติ
        $internal = $this->makeTrainer($main, 'ครูเอ', 'ภายใน', 'trainer.a@example.com', TrainerType::Internal, TrainerStatus::Approved);

        // เทรนเนอร์ภายนอก อนุมัติแล้วพร้อมใบรับรอง
        $external = $this->makeTrainer($main, 'ครูบี', 'ภายนอก', 'trainer.b@example.com', TrainerType::External, TrainerStatus::Approved, [
            'certification_name' => 'ACE Certified Personal Trainer',
            'certification_expires_at' => now()->addYear(),
            'contract_starts_at' => now()->subMonth(),
            'contract_ends_at' => now()->addYear(),
        ]);

        // เทรนเนอร์ภายนอกที่ยังรออนุมัติ ใช้ทดสอบว่าจองไม่ได้
        $this->makeTrainer($main, 'ครูซี', 'รออนุมัติ', 'trainer.c@example.com', TrainerType::External, TrainerStatus::Pending);

        foreach ([$internal, $external] as $index => $trainer) {
            for ($i = 1; $i <= 6; $i++) {
                $member = $this->makeMember($main, $trainer, $index, $i);

                MemberPackage::create([
                    'member_id' => $member->id,
                    'package_id' => $packages[1]->id,
                    'package_name' => $packages[1]->name,
                    'credits_total' => $packages[1]->credits,
                    'credits_used' => 0,
                    'price_paid' => $packages[1]->price,
                    'starts_at' => now()->toDateString(),
                    'expires_at' => now()->addDays($packages[1]->validity_days)->toDateString(),
                ]);
            }
        }

        // กลุ่มตัวอย่างให้เห็นว่าฟีเจอร์ทำงานยังไง เพดานคนมาจาก branches.max_group_size
        foreach ([$internal, $external] as $trainer) {
            $group = MemberGroup::create([
                'trainer_id' => $trainer->id,
                'branch_id' => $trainer->branch_id,
                'name' => 'กลุ่มเช้า จ-พ-ศ',
                'description' => 'ซ้อมเช้าสามวันต่อสัปดาห์',
            ]);

            $trainer->teamMembers()->take(3)->get()
                ->each(fn ($member) => $group->members()->attach($member->id, ['joined_at' => now()]));
        }

        $generator = app(SessionGenerator::class);

        foreach ([$main, $annex] as $branch) {
            $result = $generator->generateForBranch($branch, now(), now()->addDays(30));
            $this->command?->info("สร้างรอบให้ {$branch->code}: {$result['created']} รอบ");
        }
    }

    protected function makeTrainer(
        Branch $branch,
        string $firstName,
        string $lastName,
        string $email,
        TrainerType $type,
        TrainerStatus $status,
        array $extra = [],
    ): Trainer {
        $user = User::create([
            'branch_id' => $branch->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => 'password',
            'role' => UserRole::Trainer,
            'email_verified_at' => now(),
        ]);

        return Trainer::create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => $type,
            'status' => $status,
            'bio' => 'เทรนเนอร์ตัวอย่างสำหรับทดสอบระบบ',
            'specialties' => ['Functional Training', 'Strength'],
            'approved_at' => $status === TrainerStatus::Approved ? now() : null,
        ], $extra));
    }

    protected function makeMember(Branch $branch, Trainer $trainer, int $teamIndex, int $i): Member
    {
        $user = User::create([
            'branch_id' => $branch->id,
            'first_name' => 'ลูกทีม'.$i,
            'last_name' => $trainer->user->first_name,
            'email' => "member{$teamIndex}{$i}@example.com",
            'password' => 'password',
            'role' => UserRole::Member,
            'email_verified_at' => now(),
        ]);

        $member = Member::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'primary_trainer_id' => $trainer->id,
            'status' => MemberStatus::Active,
            'parq_answers' => ['heart_condition' => false, 'chest_pain' => false],
            'parq_signed_at' => now(),
            'parq_signature' => 'seeded',
        ]);

        TeamMember::create([
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'status' => 'active',
            'joined_at' => now(),
            'joined_via' => 'admin',
        ]);

        return $member;
    }
}
