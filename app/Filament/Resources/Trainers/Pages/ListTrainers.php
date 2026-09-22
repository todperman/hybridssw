<?php

namespace App\Filament\Resources\Trainers\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Trainers\TrainerResource;
use Filament\Resources\Pages\ListRecords;

class ListTrainers extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = TrainerResource::class;

    /** เทรนเนอร์สมัครเองที่หน้าบ้าน หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
