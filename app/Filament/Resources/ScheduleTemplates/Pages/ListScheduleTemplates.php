<?php

namespace App\Filament\Resources\ScheduleTemplates\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\ScheduleTemplates\ScheduleTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduleTemplates extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = ScheduleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
