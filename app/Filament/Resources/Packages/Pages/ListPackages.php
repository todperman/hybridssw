<?php

namespace App\Filament\Resources\Packages\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Packages\PackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackages extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
