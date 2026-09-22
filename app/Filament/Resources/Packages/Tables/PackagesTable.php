<?php

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('credits')
            ->columns([
                TextColumn::make('name')
                    ->label('ชื่อแพ็ก')
                    ->weight('bold')
                    ->description(fn ($record) => $record->description ?: null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->placeholder('ทุกสาขา')
                    ->sortable(),

                TextColumn::make('credits')
                    ->label('เครดิต')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state.' ครั้ง')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('ราคา')
                    ->money('THB', locale: 'th')
                    ->sortable(),

                // ตัวเลขที่คนซื้อใช้ตัดสินใจจริง ๆ คือราคาต่อครั้ง ไม่ใช่ราคารวม
                TextColumn::make('price_per_credit')
                    ->label('ตกครั้งละ')
                    ->state(fn ($record) => $record->credits > 0 ? $record->price / $record->credits : null)
                    ->money('THB', locale: 'th')
                    ->placeholder('—'),

                TextColumn::make('validity_days')
                    ->label('อายุการใช้งาน')
                    ->formatStateUsing(fn ($state) => $state.' วัน')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'ขายอยู่' : 'หยุดขาย')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('สร้างเมื่อ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('สาขา')
                    ->relationship('branch', 'name'),

                TernaryFilter::make('is_active')
                    ->label('สถานะ')
                    ->trueLabel('ขายอยู่')
                    ->falseLabel('หยุดขาย')
                    ->placeholder('ทั้งหมด'),
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
