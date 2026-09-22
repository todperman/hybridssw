<?php

namespace App\Filament\Resources\ScheduleTemplates\Schemas;

use App\Models\Branch;
use App\Models\ScheduleTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduleTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('เวลาเปิดของวันนี้')
                    ->description('หนึ่งแถวคือหนึ่งวันในสัปดาห์ ถ้าเปิดทุกวันต้องสร้าง 7 แถว')
                    ->columns(2)
                    ->schema([
                        Select::make('branch_id')
                            ->label('สาขา')
                            ->relationship('branch', 'name')
                            ->default(fn () => Branch::query()->value('id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('day_of_week')
                            ->label('วัน')
                            ->options(ScheduleTemplate::DAY_NAMES)
                            ->required(),

                        TextInput::make('name')
                            ->label('ชื่อเรียก')
                            ->placeholder('เช่น เช้า, เย็น, เต็มวัน')
                            ->helperText('ไว้แยกให้ออกเวลาวันเดียวมีหลายช่วง')
                            ->required()
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('เปิดใช้งาน')
                            ->helperText('ปิดไว้ = ข้ามวันนี้ตอนสร้างรอบ')
                            ->default(true)
                            ->inline(false),

                        TimePicker::make('start_time')
                            ->label('เปิด')
                            ->seconds(false)
                            ->required(),

                        TimePicker::make('end_time')
                            ->label('ปิด')
                            ->seconds(false)
                            ->required()
                            ->after('start_time'),
                    ]),

                Section::make('ขนาดรอบ')
                    ->columns(2)
                    ->schema([
                        TextInput::make('slot_duration_minutes')
                            ->label('ความยาวรอบ (นาที)')
                            ->helperText('ช่วงที่เหลือไม่ครบหนึ่งรอบจะถูกตัดทิ้ง')
                            ->numeric()
                            ->minValue(15)
                            ->maxValue(240)
                            ->default(60)
                            ->required(),

                        TextInput::make('capacity')
                            ->label('ที่นั่งต่อรอบ')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(fn () => Branch::query()->value('default_capacity') ?? 5)
                            ->required(),
                    ]),

                Section::make('ช่วงที่ใช้กฎนี้')
                    ->description('เว้นว่างทั้งคู่ = ใช้ตลอดไป ใส่เฉพาะตอนมีตารางฤดูกาลหรือช่วงปิดปรับปรุง')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        DatePicker::make('effective_from')->label('เริ่มใช้'),
                        DatePicker::make('effective_until')->label('ใช้ถึง')->after('effective_from'),
                    ]),
            ]);
    }
}
