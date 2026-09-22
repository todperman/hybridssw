<?php

namespace App\Filament\Resources\WorkoutSessions;

use App\Filament\Resources\WorkoutSessions\Pages\CreateWorkoutSession;
use App\Filament\Resources\WorkoutSessions\Pages\EditWorkoutSession;
use App\Filament\Resources\WorkoutSessions\Pages\ListWorkoutSessions;
use App\Filament\Resources\WorkoutSessions\Schemas\WorkoutSessionForm;
use App\Filament\Resources\WorkoutSessions\Tables\WorkoutSessionsTable;
use App\Models\WorkoutSession;
use BackedEnum;
use App\Filament\Concerns\ScopesToBranch;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkoutSessionResource extends Resource
{
    use ScopesToBranch;

    protected static ?string $model = WorkoutSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'ตารางและรอบ';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'รอบการเล่น';

    protected static ?string $modelLabel = 'รอบการเล่น';

    protected static ?string $pluralModelLabel = 'รอบการเล่น';

    public static function form(Schema $schema): Schema
    {
        return WorkoutSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkoutSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /** รอบถูกสร้างจากตารางเวลาเปิด ไม่ใช่สร้างมือทีละรอบ */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkoutSessions::route('/'),
        ];
    }
}
