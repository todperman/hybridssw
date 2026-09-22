<?php

namespace App\Filament\Resources\ScheduleTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScheduleTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('วัน')
                    ->formatStateUsing(fn ($state) => \App\Models\ScheduleTemplate::DAY_NAMES[$state] ?? '-')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('ชื่อช่วง')
                    ->weight('bold')
                    ->searchable(),

                // เวลาเปิด-ปิดอ่านคู่กันเสมอ รวมไว้ช่องเดียวจะเทียบข้ามแถวได้เร็วกว่า
                TextColumn::make('start_time')
                    ->label('ช่วงเวลา')
                    ->formatStateUsing(fn ($record) => substr((string) $record->start_time, 0, 5).' - '.substr((string) $record->end_time, 0, 5))
                    ->sortable(),

                TextColumn::make('slot_duration_minutes')
                    ->label('ความยาวรอบ')
                    ->formatStateUsing(fn ($state) => $state ? $state.' นาที' : 'ตามค่าสาขา')
                    ->toggleable(),

                TextColumn::make('capacity')
                    ->label('ที่นั่ง/รอบ')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->sortable(),

                TextColumn::make('effective_from')
                    ->label('เริ่มใช้')
                    ->date('d/m/Y')
                    ->placeholder('ทันที')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('effective_until')
                    ->label('ใช้ถึง')
                    ->date('d/m/Y')
                    ->placeholder('ไม่กำหนด')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'ใช้งาน' : 'ปิด')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
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
