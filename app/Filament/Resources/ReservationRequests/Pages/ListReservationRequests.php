<?php

namespace App\Filament\Resources\ReservationRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\ReservationRequests\ReservationRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservationRequests extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = ReservationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('รอพิจารณา')
                ->badge(fn () => ReservationRequestResource::pendingCount() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RequestStatus::Pending->value)),

            'all' => Tab::make('ทั้งหมด'),
        ];
    }
}
