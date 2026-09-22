<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = BookingResource::class;

    /** การจองเกิดที่หน้าบ้านโดยเทรนเนอร์ หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
