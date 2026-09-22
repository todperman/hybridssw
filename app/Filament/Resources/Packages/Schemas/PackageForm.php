<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name'),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('credits')
                    ->required()
                    ->numeric(),
                TextInput::make('price')
                    ->label('ราคา (บาท)')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('฿'),
                TextInput::make('validity_days')
                    ->required()
                    ->numeric()
                    ->default(90),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
