<?php

namespace App\Filament\Resources\Branches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('รหัส')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('ชื่อสาขา')
                    ->weight('bold')
                    ->description(fn ($record) => $record->address ?: null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('เบอร์โทร')
                    ->placeholder('ไม่มี')
                    ->searchable(),

                TextColumn::make('hourly_rate')
                    ->label('ราคาต่อชั่วโมง')
                    ->money('THB')
                    ->color(fn ($state) => (float) $state > 0 ? null : 'danger')
                    ->description(fn ($record) => (float) $record->hourly_rate > 0 ? null : 'ยังไม่ตั้ง จองไม่ได้')
                    ->sortable(),

                TextColumn::make('max_trainees')
                    ->label('ต่อการจอง')
                    ->formatStateUsing(fn ($record) => 'ไม่เกิน '.$record->max_trainees.' คน · '.$record->max_booking_hours.' ชม.'),

                TextColumn::make('booking_window_days')
                    ->label('จองล่วงหน้า')
                    ->formatStateUsing(fn ($state) => $state.' วัน')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'เปิดให้บริการ' : 'ปิด')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                TextColumn::make('timezone')
                    ->label('เขตเวลา')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('สร้างเมื่อ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('สถานะ')
                    ->trueLabel('เปิดให้บริการ')
                    ->falseLabel('ปิด')
                    ->placeholder('ทั้งหมด'),

                TrashedFilter::make()
                    ->label('ที่ถูกลบ'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
