<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Rules\ThaiPhone;
use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name'),
                TextInput::make('first_name')
                    ->label('ชื่อ')
                    ->required()
                    ->maxLength(120),

                TextInput::make('last_name')
                    ->label('นามสกุล')
                    ->required()
                    ->maxLength(120),
                TextInput::make('nickname')
                    ->label('ชื่อเล่น')
                    ->maxLength(60),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->options(UserRole::class)
                    ->default('member')
                    ->required(),
                TextInput::make('phone')
                    ->rules([new ThaiPhone])
                    ->tel(),
                TextInput::make('avatar_path'),
                TextInput::make('line_user_id'),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_login_at'),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
            ]);
    }
}
