<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Enums\MemberStatus;
use App\Models\Member;
use BackedEnum;
use App\Filament\Concerns\ScopesToBranch;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MemberResource extends Resource
{
    use ScopesToBranch;

    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'คนในระบบ';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'สมาชิก';

    protected static ?string $modelLabel = 'สมาชิก';

    protected static ?string $pluralModelLabel = 'สมาชิก';

    protected static ?string $recordTitleAttribute = 'member_code';

    /** จำนวนคนที่สมัครเองแล้วรออนุมัติ นับผ่านขอบเขตสาขาเดียวกับตาราง */
    public static function pendingCount(): int
    {
        return static::getEloquentQuery()->where('status', MemberStatus::Pending->value)->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::pendingCount();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'สมัครใหม่รออนุมัติ';
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /** สมาชิกสมัครเองหรือเข้าผ่านลิงก์ชวน หลังบ้านทำหน้าที่อนุมัติ ดูแลสถานะ และสิทธิ์ไม่มี Trainer */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
