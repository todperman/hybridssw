<?php

namespace App\Filament\Resources\Reservations;

use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Filament\Concerns\ScopesToBranch;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReservationResource extends Resource
{
    use ScopesToBranch;

    protected static ?string $model = Reservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'การจอง';

    protected static ?string $modelLabel = 'การจอง';

    protected static ?string $pluralModelLabel = 'การจอง';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function awaitingPaymentCount(): int
    {
        return static::getEloquentQuery()
            ->where('status', ReservationStatus::PendingPayment->value)
            ->where('hold_expires_at', '>', now())
            ->count();
    }

    public static function openRefundCount(): int
    {
        return static::getEloquentQuery()
            ->whereIn('refund_status', [RefundStatus::Pending->value, RefundStatus::Failed->value])
            ->count();
    }

    /** ตัวเลขที่เมนู = งานที่แอดมินต้องลงมือ: คืนเงินค้าง และรายการรอชำระที่อาจต้องบันทึกรับเงิน */
    public static function getNavigationBadge(): ?string
    {
        $count = static::awaitingPaymentCount() + static::openRefundCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::openRefundCount() > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'รอชำระเงินและคืนเงินค้าง';
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'view' => ViewReservation::route('/{record}'),
        ];
    }
}
