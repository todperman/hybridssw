<?php

namespace App\Filament\Resources\ScheduleExceptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScheduleExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('วันที่')
                    ->formatStateUsing(fn ($state) => $state?->locale('th')->isoFormat('ddd D MMM YYYY'))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('ประเภท')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['closed' => 'ปิดทั้งวัน', 'custom_hours' => 'เปลี่ยนเวลาเปิด', 'special_open' => 'เปิดเพิ่มพิเศษ'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'closed' => 'danger',
                        'special_open' => 'success',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('ช่วงเวลา')
                    // ปิดทั้งวันไม่มีช่วงเวลา แสดงขีดไว้ดีกว่าปล่อยว่างจนดูเหมือนข้อมูลหาย
                    ->formatStateUsing(fn ($record) => $record->start_time
                        ? substr((string) $record->start_time, 0, 5).' - '.substr((string) $record->end_time, 0, 5)
                        : '—'),

                TextColumn::make('capacity')
                    ->label('ที่นั่ง/รอบ')
                    ->placeholder('ตามปกติ')
                    ->toggleable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('เหตุผล')
                    ->placeholder('ไม่ได้ระบุ')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('บันทึกเมื่อ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
