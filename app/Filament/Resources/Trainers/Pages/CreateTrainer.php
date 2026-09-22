<?php

namespace App\Filament\Resources\Trainers\Pages;

use App\Filament\Resources\Trainers\Schemas\TrainerForm;
use App\Filament\Resources\Trainers\TrainerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainer extends CreateRecord
{
    protected static string $resource = TrainerResource::class;

    /** โหมดขนาดทีมเป็นฟิลด์ช่วยกรอก ต้องแปลงเป็นค่าจริงก่อนบันทึก */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TrainerForm::resolveTeamSize($data);
    }
}
