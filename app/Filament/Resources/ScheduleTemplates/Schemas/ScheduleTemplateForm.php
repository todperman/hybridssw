<?php

namespace App\Filament\Resources\ScheduleTemplates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('day_of_week')
                    ->required()
                    ->numeric(),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                TextInput::make('slot_duration_minutes')
                    ->required()
                    ->numeric()
                    ->default(60),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->default(5),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_until'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
