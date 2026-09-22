<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Rules\ThaiPhone;
use App\Enums\MemberStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required(),
                Select::make('primary_trainer_id')
                    ->relationship('primaryTrainer', 'id'),
                TextInput::make('member_code')
                    ->required(),
                DatePicker::make('date_of_birth'),
                TextInput::make('gender'),
                TextInput::make('emergency_contact_name'),
                TextInput::make('emergency_contact_phone')
                    ->rules([new ThaiPhone])
                    ->tel(),
                Textarea::make('health_note')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(MemberStatus::class)
                    ->required(),
                TextInput::make('no_show_count')
                    ->numeric()
                    ->default(0)
                    ->required(),
                DateTimePicker::make('no_show_reset_at'),
                DateTimePicker::make('suspended_until'),
                Textarea::make('suspension_reason')
                    ->columnSpanFull(),
            ]);
    }
}
