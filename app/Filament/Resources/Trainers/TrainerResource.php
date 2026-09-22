<?php

namespace App\Filament\Resources\Trainers;

use App\Filament\Concerns\ScopesToBranch;
use App\Filament\Resources\Trainers\Pages\CreateTrainer;
use App\Filament\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Resources\Trainers\Pages\ListTrainers;
use App\Filament\Resources\Trainers\Schemas\TrainerForm;
use App\Filament\Resources\Trainers\Tables\TrainersTable;
use App\Models\Trainer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrainerResource extends Resource
{
    use ScopesToBranch;

    protected static ?string $model = Trainer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'คนในระบบ';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'เทรนเนอร์';

    protected static ?string $modelLabel = 'เทรนเนอร์';

    protected static ?string $pluralModelLabel = 'เทรนเนอร์';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return TrainerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainersTable::configure($table);
    }

    /** ตัวเลขคนรออนุมัติบนเมนู เพื่อให้แอดมินเห็นงานค้างโดยไม่ต้องเปิดหน้า */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** เทรนเนอร์ต้องสมัครเองที่หน้าบ้าน หลังบ้านทำหน้าที่อนุมัติและตั้งโควตาเท่านั้น */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainers::route('/'),
            'edit' => EditTrainer::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
