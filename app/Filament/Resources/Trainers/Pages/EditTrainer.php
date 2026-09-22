<?php

namespace App\Filament\Resources\Trainers\Pages;

use App\Filament\Resources\Trainers\Schemas\TrainerForm;
use App\Filament\Resources\Trainers\TrainerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainer extends EditRecord
{
    protected static string $resource = TrainerResource::class;

    /** โหมดขนาดทีมเป็นฟิลด์ช่วยกรอก ต้องแปลงเป็นค่าจริงก่อนบันทึก */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TrainerForm::resolveTeamSize($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
