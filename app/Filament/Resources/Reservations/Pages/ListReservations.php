<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Reservations\ReservationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = ReservationResource::class;

    /** การจองเกิดจากหน้าบ้านเท่านั้น ผู้จองต้องเป็น Trainee หรือ Trainer ตามข้อกำหนด */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        // Filament ส่งค่าเข้า closure ตามชื่อพารามิเตอร์ ต้องชื่อ $query เท่านั้น
        return [
            'upcoming' => Tab::make('กำลังจะถึง')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', ReservationStatus::holding())
                    ->where('ends_at', '>', now())),

            'today' => Tab::make('วันนี้')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', ReservationStatus::holding())
                    ->whereDate('starts_at', today())),

            'pending' => Tab::make('รอชำระเงิน')
                ->badge(fn () => ReservationResource::awaitingPaymentCount() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReservationStatus::PendingPayment->value)),

            'refunds' => Tab::make('คืนเงินค้าง')
                ->badge(fn () => ReservationResource::openRefundCount() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('refund_status', [RefundStatus::Pending->value, RefundStatus::Failed->value])),

            'all' => Tab::make('ทั้งหมด')
                ->modifyQueryUsing(fn (Builder $query) => $query->reorder()->orderByDesc('starts_at')),
        ];
    }
}
