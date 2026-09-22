<?php

namespace App\Filament\Resources\MemberGroups;

use App\Filament\Resources\MemberGroups\Pages\CreateMemberGroup;
use App\Filament\Resources\MemberGroups\Pages\EditMemberGroup;
use App\Filament\Resources\MemberGroups\Pages\ListMemberGroups;
use App\Filament\Resources\MemberGroups\Schemas\MemberGroupForm;
use App\Filament\Resources\MemberGroups\Tables\MemberGroupsTable;
use App\Models\MemberGroup;
use BackedEnum;
use App\Filament\Concerns\ScopesToBranch;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberGroupResource extends Resource
{
    use ScopesToBranch;

    protected static ?string $model = MemberGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'คนในระบบ';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'กลุ่มลูกทีม';

    protected static ?string $modelLabel = 'กลุ่มลูกทีม';

    protected static ?string $pluralModelLabel = 'กลุ่มลูกทีม';

    protected static ?string $recordTitleAttribute = 'name';

    /** เทรนเนอร์เป็นคนสร้างกลุ่มเอง หลังบ้านมีหน้าที่ตั้งเพดานจำนวนสมาชิก */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MemberGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberGroups::route('/'),
            'edit' => EditMemberGroup::route('/{record}/edit'),
        ];
    }
}
