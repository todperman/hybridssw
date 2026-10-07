<?php

namespace App\Filament\Resources\ScheduleExceptions\Pages;

use App\Filament\Resources\ScheduleExceptions\ScheduleExceptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduleException extends CreateRecord
{
    protected static string $resource = ScheduleExceptionResource::class;

    /** เก็บว่าใครตั้งวันพิเศษนี้ ไม่ให้กรอกเองจากฟอร์ม */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
