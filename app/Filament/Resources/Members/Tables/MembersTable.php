<?php

namespace App\Filament\Resources\Members\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use App\Enums\MemberStatus;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('ชื่อ')
                    ->weight('bold')
                    ->description(fn ($record) => $record->user?->nickname ?: $record->user?->email)
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'user',
                        fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('nickname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                    ))
                    ->sortable(),

                TextColumn::make('member_code')
                    ->label('รหัสสมาชิก')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('คัดลอกรหัสแล้ว')
                    ->searchable(),

                TextColumn::make('primaryTrainer.user.name')
                    ->label('เทรนเนอร์')
                    ->placeholder('ยังไม่มี')
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    // ระงับอยู่ต้องเห็นวันปลดล็อกด้วย ไม่งั้นต้องกดเข้าไปดูทีละคน
                    ->description(fn ($record) => $record->suspended_until?->isFuture()
                        ? 'ถึง '.$record->suspended_until->format('d/m/Y')
                        : null)
                    ->badge()
                    ->sortable(),

                TextColumn::make('no_show_count')
                    ->label('ไม่มาตามนัด')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state) => $state.' ครั้ง')
                    ->sortable(),

                TextColumn::make('date_of_birth')
                    ->label('อายุ')
                    ->formatStateUsing(fn ($state) => $state ? $state->age.' ปี' : null)
                    ->placeholder('ไม่ระบุ')
                    ->sortable()
                    ->toggleable(),

                // ข้อมูลติดต่อฉุกเฉินเป็นข้อมูลอ่อนไหว ซ่อนไว้ก่อน เปิดดูเมื่อจำเป็น
                TextColumn::make('emergency_contact_name')
                    ->label('ผู้ติดต่อฉุกเฉิน')
                    ->description(fn ($record) => $record->emergency_contact_phone ?: null)
                    ->placeholder('ไม่มี')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('สมัครเมื่อ')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('สถานะ')
                    ->options(MemberStatus::class)
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label('สาขา')
                    ->relationship('branch', 'name'),

                SelectFilter::make('primary_trainer_id')
                    ->label('เทรนเนอร์')
                    ->relationship('primaryTrainer.user', 'name')
                    ->searchable(),

                // คัดคนที่ควรตามก่อน ไม่ต้องเรียงแล้วไล่ดูเอง
                Filter::make('has_no_shows')
                    ->label('เคยไม่มาตามนัด')
                    ->query(fn ($query) => $query->where('no_show_count', '>', 0)),

                Filter::make('suspended')
                    ->label('กำลังถูกระงับ')
                    ->query(fn ($query) => $query->whereNotNull('suspended_until')
                        ->where('suspended_until', '>', now())),

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
