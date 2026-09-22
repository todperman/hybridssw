<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required(),
                Select::make('workout_session_id')
                    ->relationship('workoutSession', 'id')
                    ->required(),
                Select::make('member_id')
                    ->relationship('member', 'id')
                    ->required(),
                Select::make('trainer_id')
                    ->relationship('trainer', 'id')
                    ->required(),
                TextInput::make('booked_by_user_id')
                    ->numeric(),
                Select::make('status')
                    ->options(BookingStatus::class)
                    ->default('booked')
                    ->required(),
                TextInput::make('waitlist_position')
                    ->numeric(),
                DateTimePicker::make('promoted_at'),
                DateTimePicker::make('confirm_deadline_at'),
                DateTimePicker::make('checked_in_at'),
                TextInput::make('checked_in_by')
                    ->numeric(),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('cancelled_by_user_id')
                    ->numeric(),
                TextInput::make('cancellation_reason'),
                Toggle::make('cancelled_late')
                    ->required(),
                Select::make('member_package_id')
                    ->relationship('memberPackage', 'id'),
                Toggle::make('credit_consumed')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('active_member_key')
                    ->numeric(),
            ]);
    }
}
