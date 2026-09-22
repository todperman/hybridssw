<?php

namespace App\Filament\Resources\WorkoutSessions\Schemas;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WorkoutSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required(),
                Select::make('schedule_template_id')
                    ->relationship('scheduleTemplate', 'name'),
                DatePicker::make('date')
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->required(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->default(5),
                TextInput::make('booked_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('waitlist_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('mode')
                    ->options(SessionMode::class)
                    ->default('shared')
                    ->required(),
                Select::make('claimed_by_trainer_id')
                    ->relationship('claimedByTrainer', 'id'),
                Select::make('status')
                    ->options(SessionStatus::class)
                    ->default('open')
                    ->required(),
                TextInput::make('close_reason'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
