<?php

namespace App\Filament\Resources\MemberGroups\Tables;

use App\Models\MemberGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('ชื่อกลุ่ม')
                    ->description(fn (MemberGroup $r) => $r->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('trainer.user.name')
                    ->label('เทรนเนอร์')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->toggleable(),

                TextColumn::make('members_count')
                    ->label('สมาชิก')
                    ->counts('members')
                    ->badge()
                    ->formatStateUsing(fn ($state, MemberGroup $r) => "{$state} / {$r->capacity()}")
                    ->color(fn ($state, MemberGroup $r) => $state >= $r->capacity() ? 'warning' : 'success'),

                TextColumn::make('max_members')
                    ->label('เพดานเฉพาะกลุ่ม')
                    ->placeholder('ใช้ค่าของสาขา')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('เปิดใช้งาน')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('branch')->label('สาขา')->relationship('branch', 'name'),
                SelectFilter::make('trainer')->label('เทรนเนอร์')->relationship('trainer.user', 'name')->searchable(),

                Filter::make('has_override')
                    ->label('มีเพดานเฉพาะกลุ่ม')
                    ->query(fn (Builder $q) => $q->whereNotNull('max_members')),
            ])
            ->recordActions([
                EditAction::make()->label('ตั้งเพดาน'),
            ]);
    }
}
