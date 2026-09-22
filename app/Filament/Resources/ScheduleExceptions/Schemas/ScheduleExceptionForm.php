<?php

namespace App\Filament\Resources\ScheduleExceptions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ScheduleExceptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required(),
                DatePicker::make('date')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('closed'),
                TimePicker::make('start_time'),
                TimePicker::make('end_time'),
                TextInput::make('capacity')
                    ->numeric(),
                TextInput::make('reason'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
