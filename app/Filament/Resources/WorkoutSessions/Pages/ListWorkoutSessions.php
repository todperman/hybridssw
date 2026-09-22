<?php

namespace App\Filament\Resources\WorkoutSessions\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\WorkoutSessions\WorkoutSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkoutSessions extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = WorkoutSessionResource::class;

    /** รอบถูกสร้างจากตารางเวลาเปิด ใช้ปุ่มสร้างรอบล่วงหน้าในตารางแทน หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
