<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * รายการรับเงินทั้งหมด แยกรายการที่ต้องตรวจสอบไว้ให้เห็นก่อน
 * เช่นชำระหลังหมดเวลาแล้วเวลาถูกคนอื่นจองไป ต้องคืนเงินให้ผู้ชำระ
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'การชำระเงิน';

    protected static ?string $modelLabel = 'การชำระเงิน';

    protected static ?string $pluralModelLabel = 'การชำระเงิน';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->whereHas('reservation', fn ($q) => $q->where('branch_id', $user->branch_id ?? 0));
    }

    public static function reviewCount(): int
    {
        return static::getEloquentQuery()->where('needs_review', true)->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::reviewCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'ต้องตรวจสอบ';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['reservation', 'payer.user', 'recordedBy']))
            ->columns([
                TextColumn::make('created_at')->label('เวลา')->dateTime('d/m/Y H:i')->sortable(),

                TextColumn::make('reservation.reference')
                    ->label('การจอง')
                    ->description(fn (Payment $record) => $record->reservation->dateLabel().' '.$record->reservation->timeLabel())
                    ->url(fn (Payment $record) => ReservationResource::getUrl('view', ['record' => $record->reservation]))
                    ->searchable(),

                TextColumn::make('payer.user.name')->label('ผู้ชำระเงิน')->searchable(),

                TextColumn::make('amount')->label('ยอด')->money('THB')->sortable(),

                TextColumn::make('provider')
                    ->label('ช่องทาง')
                    ->formatStateUsing(fn (Payment $record) => $record->providerLabel())
                    ->description(fn (Payment $record) => $record->recordedBy?->name),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge()
                    ->description(fn (Payment $record) => $record->needs_review ? 'ต้องตรวจสอบ' : $record->failure_message),

                TextColumn::make('note')->label('หมายเหตุ')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('สถานะ')->options(PaymentStatus::class),
            ])
            ->recordActions([
                Action::make('refund')
                    ->label('คืนเงิน')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->needs_review && $record->status === PaymentStatus::Succeeded)
                    ->requiresConfirmation()
                    ->modalHeading('คืนเงินรายการนี้เต็มจำนวน')
                    ->modalDescription(fn (Payment $record) => 'คืน ฿'.number_format((float) $record->amount, 2).' ให้ '.$record->payer?->user?->name.' ผ่านช่องทางเดิม')
                    ->schema([Textarea::make('reason')->label('เหตุผล')->required()->rows(2)
                        ->default('ชำระหลังหมดเวลาและช่วงเวลาถูกจองไปแล้ว')])
                    ->action(fn (Payment $record, array $data) => static::resolve($record, $data['reason'], true)),

                Action::make('markReviewed')
                    ->label('ตรวจแล้ว ไม่ต้องคืน')
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn (Payment $record) => $record->needs_review)
                    ->schema([Textarea::make('reason')->label('เหตุผล')->required()->rows(2)])
                    ->action(fn (Payment $record, array $data) => static::resolve($record, $data['reason'], false)),
            ]);
    }

    protected static function resolve(Payment $payment, string $reason, bool $refund): void
    {
        try {
            $result = app(PaymentService::class)->resolveReview($payment, auth()->user(), $reason, $refund);

            Notification::make()
                ->title($refund ? 'สร้างรายการคืนเงินแล้ว' : 'ปิดรายการตรวจสอบแล้ว')
                ->body($result ? 'สถานะคืนเงิน: '.$result->status->label() : null)
                ->success()
                ->send();
        } catch (ReservationException $e) {
            Notification::make()->title('ทำรายการไม่สำเร็จ')->body($e->getMessage())->danger()->send();
        }
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
