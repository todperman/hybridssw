<?php

namespace Tests\Feature;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Livewire\TrainerRegistration;
use App\Models\Branch;
use App\Models\Trainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function branch(string $code = 'B1'): Branch
    {
        return Branch::create(['code' => $code, 'name' => 'สาขา '.$code]);
    }

    protected function fillAccount(\Livewire\Features\SupportTesting\Testable $c): \Livewire\Features\SupportTesting\Testable
    {
        return $c->set('first_name', 'ทดสอบ')
            ->set('last_name', 'สมัคร')
            ->set('phone', '0800000000')
            ->set('email', 'apply@example.test')
            ->set('password', 'Hybrid#2026ssw')
            ->set('password_confirmation', 'Hybrid#2026ssw');
    }

    #[Test]
    public function only_the_external_type_is_offered_by_default(): void
    {
        // ค่าเริ่มต้นซ่อนเทรนเนอร์ภายในไว้ ให้แอดมินสร้างให้จากหลังบ้านแทน
        $this->assertSame([TrainerType::External], TrainerType::orderedForRegistration());
    }

    #[Test]
    public function the_internal_type_can_be_opened_up_from_config(): void
    {
        config(['gym.registration.allow_internal' => true]);

        $this->assertSame(
            [TrainerType::External, TrainerType::Internal],
            TrainerType::orderedForRegistration(),
        );
    }

    #[Test]
    public function trainer_defaults_are_read_from_config(): void
    {
        config([
            'gym.trainer_defaults.external.seats_per_session' => 5,
            'gym.trainer_defaults.external.max_team_size' => 25,
            'gym.trainer_defaults.internal.max_team_size' => null,
        ]);

        $this->assertSame(5, TrainerType::External->defaultMaxSeatsPerSession());
        $this->assertSame(25, TrainerType::External->defaultMaxTeamSize());
        $this->assertNull(TrainerType::Internal->defaultMaxTeamSize(), 'null คือไม่จำกัด');
    }

    #[Test]
    public function an_unlimited_team_never_reports_a_limit(): void
    {
        config(['gym.trainer_defaults.internal.max_team_size' => null]);

        $branch = $this->branch();
        $trainer = Trainer::create([
            'user_id' => \App\Models\User::create([
                'branch_id' => $branch->id, 'first_name' => 'ครู', 'last_name' => 'ภายใน',
                'email' => 'inside@example.test', 'password' => 'password',
            ])->id,
            'branch_id' => $branch->id,
            'type' => TrainerType::Internal,
            'status' => TrainerStatus::Approved,
        ]);

        $this->assertNull($trainer->maxTeamSize());
        $this->assertTrue($trainer->hasUnlimitedTeam());
        $this->assertSame('ไม่จำกัด', $trainer->teamLimitLabel());
    }

    #[Test]
    public function the_branch_selector_hides_when_there_is_only_one_branch(): void
    {
        $this->branch();

        $component = Livewire::test(TrainerRegistration::class);

        $this->assertFalse($component->instance()->showsBranchSelector());
        $this->assertNotNull($component->instance()->branch_id, 'ต้องผูกสาขาให้เสมอถึงจะไม่แสดงตัวเลือก');
    }

    #[Test]
    public function the_branch_selector_can_be_switched_off_by_config(): void
    {
        $this->branch('B1');
        $this->branch('B2');

        config(['gym.registration.show_branch_selector' => false]);

        $this->assertFalse(Livewire::test(TrainerRegistration::class)->instance()->showsBranchSelector());
    }

    #[Test]
    public function turning_off_identity_verification_removes_the_third_step(): void
    {
        config([
            'gym.registration.require_identity_verification' => false,
            'gym.registration.allow_internal' => true,
        ]);
        $this->branch();

        $component = Livewire::test(TrainerRegistration::class);

        $this->assertSame(2, $component->instance()->lastStep());
        $this->assertCount(2, $component->instance()->steps());
    }

    #[Test]
    public function without_verification_an_internal_trainer_still_waits_for_approval(): void
    {
        // ปิดการยืนยันตัวตนแล้วใครก็อ้างว่าเป็นคนภายในได้ จึงต้องไม่อนุมัติอัตโนมัติ
        config([
            'gym.registration.require_identity_verification' => false,
            'gym.registration.allow_internal' => true,
        ]);
        $branch = $this->branch();

        $component = Livewire::test(TrainerRegistration::class);
        $this->fillAccount($component)
            ->set('branch_id', $branch->id)
            ->set('type', TrainerType::Internal->value)
            ->call('register');

        $trainer = Trainer::firstOrFail();

        $this->assertSame(TrainerType::Internal, $trainer->type);
        $this->assertSame(TrainerStatus::Pending, $trainer->status);
        $this->assertNull($trainer->approved_at);
    }

    #[Test]
    public function with_verification_a_correct_staff_code_approves_immediately(): void
    {
        config([
            'gym.registration.require_identity_verification' => true,
            'gym.registration.allow_internal' => true,
            'gym.internal_trainer_code' => 'SECRET-CODE',
        ]);
        $branch = $this->branch();

        $component = Livewire::test(TrainerRegistration::class);
        $this->fillAccount($component)
            ->set('branch_id', $branch->id)
            ->set('type', TrainerType::Internal->value)
            ->set('internal_code', 'SECRET-CODE')
            ->call('register');

        $this->assertSame(TrainerStatus::Approved, Trainer::firstOrFail()->status);
    }

    #[Test]
    public function with_verification_a_wrong_staff_code_is_rejected(): void
    {
        config([
            'gym.registration.require_identity_verification' => true,
            'gym.registration.allow_internal' => true,
            'gym.internal_trainer_code' => 'SECRET-CODE',
        ]);
        $branch = $this->branch();

        $component = Livewire::test(TrainerRegistration::class);
        $this->fillAccount($component)
            ->set('branch_id', $branch->id)
            ->set('type', TrainerType::Internal->value)
            ->set('internal_code', 'WRONG')
            ->call('register')
            ->assertHasErrors('internal_code');

        $this->assertSame(0, Trainer::count());
    }
}
