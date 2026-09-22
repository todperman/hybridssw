<?php

namespace Tests\Feature;

use App\Enums\TrainerType;
use App\Filament\Resources\Trainers\Schemas\TrainerForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class TrainerTeamSizeTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    #[Test]
    public function an_empty_value_falls_back_to_the_type_default(): void
    {
        config(['gym.trainer_defaults.external.max_team_size' => 20]);

        $trainer = $this->makeTrainer($this->makeBranch(), [
            'type' => TrainerType::External,
            'max_team_size' => null,
        ]);

        $this->assertSame(20, $trainer->maxTeamSize());
        $this->assertFalse($trainer->hasUnlimitedTeam());
    }

    #[Test]
    public function zero_means_unlimited_even_when_the_type_has_a_cap(): void
    {
        config(['gym.trainer_defaults.external.max_team_size' => 20]);

        $trainer = $this->makeTrainer($this->makeBranch(), [
            'type' => TrainerType::External,
            'max_team_size' => 0,
        ]);

        $this->assertNull($trainer->maxTeamSize());
        $this->assertTrue($trainer->hasUnlimitedTeam());
        $this->assertSame('ไม่จำกัด', $trainer->teamLimitLabel());
    }

    #[Test]
    public function a_number_is_used_as_written(): void
    {
        $trainer = $this->makeTrainer($this->makeBranch(), [
            'type' => TrainerType::External,
            'max_team_size' => 7,
        ]);

        $this->assertSame(7, $trainer->maxTeamSize());
        $this->assertSame('7', $trainer->teamLimitLabel());
    }

    #[Test]
    public function the_form_turns_each_mode_into_the_right_stored_value(): void
    {
        $this->assertNull(
            TrainerForm::resolveTeamSize(['team_size_mode' => 'default', 'max_team_size' => 9])['max_team_size'],
        );

        $this->assertSame(
            0,
            TrainerForm::resolveTeamSize(['team_size_mode' => 'unlimited', 'max_team_size' => 9])['max_team_size'],
        );

        $this->assertSame(
            9,
            TrainerForm::resolveTeamSize(['team_size_mode' => 'custom', 'max_team_size' => '9'])['max_team_size'],
        );

        // ฟิลด์ช่วยกรอกต้องไม่หลุดไปถึงชั้นบันทึก ไม่งั้นจะพยายามเขียนคอลัมน์ที่ไม่มีอยู่
        $this->assertArrayNotHasKey(
            'team_size_mode',
            TrainerForm::resolveTeamSize(['team_size_mode' => 'unlimited', 'max_team_size' => null]),
        );
    }
}
