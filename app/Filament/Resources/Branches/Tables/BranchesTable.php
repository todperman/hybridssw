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

                // รวมสองค่าที่ใช้คู่กันเสมอไว้ช่องเดียว ตารางจะได้ไม่ยาวเป็นหางว่าว
                TextColumn::make('default_capacity')
                    ->label('รอบมาตรฐาน')
                    ->formatStateUsing(fn ($record) => $record->default_capacity.' ที่ · '.$record->slot_duration_minutes.' นาที')
                    ->sortable(),

                TextColumn::make('session_horizon_days')
                    ->label('สร้างรอบล่วงหน้า')
                    ->formatStateUsing(fn ($state) => $state.' วัน')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'เปิดให้บริการ' : 'ปิด')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                // ค่าตั้งของกติกาการจอง นาน ๆ ดูที ซ่อนไว้ก่อนแต่ยังเปิดดูได้
                TextColumn::make('cancellation_cutoff_hours')
                    ->label('ยกเลิกฟรีก่อน')
                    ->formatStateUsing(fn ($state) => $state.' ชม.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('waitlist_confirm_minutes')
                    ->label('ยืนยันคิวสำรองใน')
                    ->formatStateUsing(fn ($state) => $state.' นาที')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('no_show_strike_limit')
                    ->label('ไม่มาได้กี่ครั้ง')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('no_show_suspension_days')
                    ->label('ระงับกี่วัน')
                    ->toggleable(isToggledHiddenByDefault: true),

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
