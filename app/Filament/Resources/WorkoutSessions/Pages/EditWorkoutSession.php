<?php

namespace App\Filament\Resources\WorkoutSessions\Pages;

use App\Filament\Resources\WorkoutSessions\WorkoutSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkoutSession extends EditRecord
{
    protected static string $resource = WorkoutSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
