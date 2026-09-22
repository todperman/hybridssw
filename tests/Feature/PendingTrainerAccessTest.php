<?php

namespace Tests\Feature;

use App\Enums\TrainerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class PendingTrainerAccessTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    #[Test]
    public function a_pending_trainer_is_sent_to_the_waiting_page(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['status' => TrainerStatus::Pending]);

        foreach (['trainer.schedule', 'trainer.team', 'trainer.insights'] as $route) {
            $this->actingAs($trainer->user)
                ->get(route($route))
                ->assertRedirect(route('trainer.pending'));
        }
    }

    #[Test]
    public function the_waiting_page_opens_for_a_pending_trainer(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['status' => TrainerStatus::Pending]);

        $this->actingAs($trainer->user)
            ->get(route('trainer.pending'))
            ->assertOk()
            ->assertSee('รอแอดมินอนุมัติ');
    }

    #[Test]
    public function an_approved_trainer_never_lands_on_the_waiting_page(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['status' => TrainerStatus::Approved]);

        $this->actingAs($trainer->user)
            ->get(route('trainer.pending'))
            ->assertRedirect(route('trainer.schedule'));

        $this->actingAs($trainer->user)
            ->get(route('trainer.schedule'))
            ->assertOk();
    }

    #[Test]
    public function the_dashboard_routes_a_pending_trainer_to_the_waiting_page(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['status' => TrainerStatus::Pending]);

        $this->actingAs($trainer->user)
            ->get(route('dashboard'))
            ->assertRedirect(route('trainer.pending'));
    }

    #[Test]
    public function a_suspended_trainer_is_also_held_on_the_waiting_page(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch, ['status' => TrainerStatus::Suspended]);

        $this->actingAs($trainer->user)
            ->get(route('trainer.schedule'))
            ->assertRedirect(route('trainer.pending'));

        $this->actingAs($trainer->user)
            ->get(route('trainer.pending'))
            ->assertOk()
            ->assertSee('บัญชีถูกระงับชั่วคราว');
    }
}
