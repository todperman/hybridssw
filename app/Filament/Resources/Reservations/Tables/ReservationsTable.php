<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationActions;
use App\Models\Reservation;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'trainer.user', 'payer.user'])->withCount('participants'))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('วันเวลา')
                    ->formatStateUsing(fn (Reservation $record) => $record->dateLabel())
                    ->description(fn (Reservation $record) => $record->timeLabel().' · '.$record->hours.' ชม.')
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('เลขการจอง')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('trainer.user.name')
                    ->label('Trainer')
                    ->placeholder('ไม่มี Trainer')
                    ->searchable(),

                TextColumn::make('payer.user.name')
                    ->label('ผู้ชำระเงิน')
                    ->description(fn (Reservation $record) => $record->participants_count.' คน')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('ยอด')
                    ->money('THB')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge()
                    ->description(fn (Reservation $record) => $record->status === ReservationStatus::PendingPayment && $record->hold_expires_at
                        ? 'ถึง '.$record->hold_expires_at->format('H:i')
                        : null),

                TextColumn::make('refund_status')
                    ->label('คืนเงิน')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_via')
                    ->label('จองผ่าน')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Reservation::VIA_TRAINER => 'Trainer',
                        Reservation::VIA_TRAINEE => 'Trainee',
                        default => 'แอดมิน',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('จองเมื่อ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('สถานะ')
                    ->options(ReservationStatus::class)
                    ->multiple(),

                SelectFilter::make('refund_status')
                    ->label('คืนเงิน')
                    ->options(RefundStatus::class),

                SelectFilter::make('trainer_id')
                    ->label('Trainer')
                    ->relationship('trainer.user', 'name')
                    ->searchable(),

                SelectFilter::make('branch_id')
                    ->label('สาขา')
                    ->relationship('branch', 'name'),

                Filter::make('date')
                    ->label('วันที่ใช้งาน')
                    ->schema([DatePicker::make('on')->label('วันที่ใช้งาน')->native(false)])
                    ->query(fn ($query, array $data) => $query->when($data['on'] ?? null, fn ($q, $on) => $q->whereDate('starts_at', $on)))
                    ->indicateUsing(fn (array $data) => ($data['on'] ?? null) ? 'วันที่ '.\Carbon\Carbon::parse($data['on'])->format('d/m/Y') : null),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ReservationActions::all()),
            ]);
    }
}
