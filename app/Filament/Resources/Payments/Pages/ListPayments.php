<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'review' => Tab::make('ต้องตรวจสอบ')
                ->badge(fn () => PaymentResource::reviewCount() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('needs_review', true)),

            'all' => Tab::make('ทั้งหมด'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return PaymentResource::reviewCount() > 0 ? 'review' : 'all';
    }
}
