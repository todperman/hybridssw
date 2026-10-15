<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('สร้างคูปอง')];
    }
}
