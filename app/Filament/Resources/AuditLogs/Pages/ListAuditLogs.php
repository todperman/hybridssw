<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
