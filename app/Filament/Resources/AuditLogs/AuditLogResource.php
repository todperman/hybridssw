<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** ประวัติการเปลี่ยนแปลงที่ต้องตรวจย้อนหลังได้ อ่านอย่างเดียว ไม่มีใครแก้หรือลบได้จากหน้านี้ */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'ประวัติการเปลี่ยนแปลง';

    protected static ?string $modelLabel = 'ประวัติ';

    protected static ?string $pluralModelLabel = 'ประวัติการเปลี่ยนแปลง';

    /** ประวัติข้ามสาขาได้ เปิดให้เฉพาะคนที่เห็นทุกสาขา */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canSeeAllBranches();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['actor', 'subject']))
            ->columns([
                TextColumn::make('created_at')->label('เวลา')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('action')->label('รายการ')->formatStateUsing(fn (AuditLog $record) => $record->actionLabel())->badge()->color('gray'),
                TextColumn::make('subject_id')
                    ->label('เกี่ยวกับ')
                    ->formatStateUsing(fn (AuditLog $record) => match (true) {
                        $record->subject instanceof Reservation => 'การจอง '.$record->subject->reference,
                        $record->subject instanceof Member => 'สมาชิก '.$record->subject->user?->name,
                        default => class_basename($record->subject_type).' #'.$record->subject_id,
                    }),
                TextColumn::make('actor.name')->label('ผู้ทำ')->placeholder('ระบบ'),
                TextColumn::make('reason')->label('เหตุผล')->wrap()->limit(80)->placeholder('—'),
                TextColumn::make('after')->label('รายละเอียด')
                    ->formatStateUsing(fn (AuditLog $record) => $record->changeSummary())
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')->label('รายการ')->options(fn () => AuditLog::query()->distinct()->pluck('action')
                    ->mapWithKeys(fn ($a) => [$a => (new AuditLog(['action' => $a]))->actionLabel()])
                    ->all()),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
