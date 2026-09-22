<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Rules\ThaiPhone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('phone')
                    ->rules([ThaiPhone::anyLine()])
                    ->tel(),
                TextInput::make('address'),
                TextInput::make('timezone')
                    ->required()
                    ->default('Asia/Bangkok'),
                TextInput::make('default_capacity')
                    ->required()
                    ->numeric()
                    ->default(5),

                TextInput::make('max_group_size')
                    ->label('จำนวนลูกทีมสูงสุดต่อกลุ่ม')
                    ->helperText('เทรนเนอร์สร้างกลุ่มเองได้ แต่จำนวนคนต่อกลุ่มถูกจำกัดด้วยค่านี้')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(50)
                    ->required()
                    ->default(5),
                TextInput::make('slot_duration_minutes')
                    ->required()
                    ->numeric()
                    ->default(60),
                TextInput::make('cancellation_cutoff_hours')
                    ->required()
                    ->numeric()
                    ->default(4),
                TextInput::make('waitlist_confirm_minutes')
                    ->required()
                    ->numeric()
                    ->default(30),
                TextInput::make('no_show_strike_limit')
                    ->required()
                    ->numeric()
                    ->default(3),
                TextInput::make('no_show_suspension_days')
                    ->required()
                    ->numeric()
                    ->default(7),
                TextInput::make('session_horizon_days')
                    ->required()
                    ->numeric()
                    ->default(60),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
