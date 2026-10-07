<?php

namespace App\Filament\Resources\ScheduleExceptions\Schemas;

use App\Models\Branch;
use App\Models\ScheduleException;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * วันพิเศษที่ทับเวลาเปิดประจำสัปดาห์
 * ปิดหรือลดเวลาไม่กระทบการจองที่มีอยู่แล้ว ต้องเลื่อนหรือยกเลิกการจองเหล่านั้นเองจากหน้าการจอง
 */
class ScheduleExceptionForm
{
    public static function configure(Schema $schema): Schema
    {
        $needsHours = fn (Get $get) => $get('type') !== ScheduleException::TYPE_CLOSED;

        return $schema
            ->columns(2)
            ->components([
                Select::make('branch_id')
                    ->label('สาขา')
                    ->relationship('branch', 'name')
                    ->default(fn () => Branch::query()->value('id'))
                    ->required(),
                DatePicker::make('date')
                    ->label('วันที่')
                    ->native(false)
                    ->required(),
                Select::make('type')
                    ->label('ประเภท')
                    ->options(ScheduleException::typeOptions())
                    ->default(ScheduleException::TYPE_CLOSED)
                    ->helperText('ปิดหรือลดเวลาไม่ยกเลิกการจองที่มีอยู่ให้อัตโนมัติ ต้องจัดการเองที่หน้าการจอง')
                    ->live()
                    ->required(),
                TextInput::make('reason')
                    ->label('เหตุผล')
                    ->placeholder('เช่น วันหยุดนักขัตฤกษ์ ปิดซ่อมบำรุง'),
                TimePicker::make('start_time')
                    ->label('เปิด')
                    ->seconds(false)
                    ->visible($needsHours)
                    ->required($needsHours),
                TimePicker::make('end_time')
                    ->label('ปิด')
                    ->seconds(false)
                    ->after('start_time')
                    ->visible($needsHours)
                    ->required($needsHours),
            ]);
    }
}
