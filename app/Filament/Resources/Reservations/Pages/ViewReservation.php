<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationActions;
use App\Filament\Resources\Reservations\ReservationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    public function getTitle(): string
    {
        return 'การจอง '.$this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return array_map(
            // หลังทำรายการให้โหลดข้อมูลใหม่ ประวัติและสถานะบนหน้าจะได้ตรง
            fn ($action) => $action->after(fn () => $this->record->refresh()),
            ReservationActions::all(),
        );
    }
}
