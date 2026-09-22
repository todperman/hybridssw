<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => null),

                // ชื่อเล่นอยู่ใต้ชื่อจริง เพราะหน้างานเรียกกันด้วยชื่อเล่น
                TextColumn::make('name')
                    ->label('ชื่อ')
                    ->weight('bold')
                    ->description(fn ($record) => $record->nickname ?: null)
                    ->searchable(['first_name', 'last_name', 'nickname'])
                    ->sortable(),

                TextColumn::make('email')
                    ->label('อีเมล')
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->copyMessage('คัดลอกอีเมลแล้ว')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('เบอร์โทร')
                    ->placeholder('ไม่มี')
                    ->searchable(),

                TextColumn::make('role')
                    ->label('บทบาท')
                    ->badge()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('สาขา')
                    ->placeholder('ทุกสาขา')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'ใช้งาน' : 'ปิดใช้งาน')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                TextColumn::make('last_login_at')
                    ->label('เข้าใช้ล่าสุด')
                    ->since()
                    ->placeholder('ยังไม่เคยเข้า')
                    ->sortable(),

                // คอลัมน์ที่นาน ๆ ใช้ที ซ่อนไว้ก่อนให้ตารางอ่านง่าย แต่ยังเปิดดูได้
                TextColumn::make('email_verified_at')
                    ->label('ยืนยันอีเมล')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('ยังไม่ยืนยัน')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('line_user_id')
                    ->label('LINE ID')
                    ->placeholder('ไม่ได้ผูก')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('สร้างเมื่อ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('บทบาท')
                    ->options(UserRole::class)
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label('สาขา')
                    ->relationship('branch', 'name'),

                TernaryFilter::make('is_active')
                    ->label('สถานะ')
                    ->trueLabel('ใช้งานอยู่')
                    ->falseLabel('ปิดใช้งาน')
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
