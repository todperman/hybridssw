<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Models\Branch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('รายละเอียดแพ็กเกจ')
                    ->description('รายการที่เปิดขาย ยังไม่ผูกกับใคร ใช้เป็นแม่แบบตอนออกให้ลูกทีมในหน้าลูกทีม')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('ชื่อแพ็กเกจ')
                            ->placeholder('เช่น รายปี, 10 ครั้ง, ทดลอง')
                            ->required()
                            ->maxLength(255),

                        Select::make('branch_id')
                            ->label('สาขา')
                            ->relationship('branch', 'name')
                            ->default(fn () => Branch::query()->value('id'))
                            ->helperText('เว้นว่าง = ใช้ได้ทุกสาขา')
                            ->searchable()
                            ->preload(),

                        Textarea::make('description')
                            ->label('คำอธิบาย')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('จำนวนครั้งและอายุ')
                    ->columns(3)
                    ->schema([
                        TextInput::make('credits')
                            ->label('จำนวนครั้งที่จองได้')
                            ->helperText('ตัดครั้งละ 1 ทุกครั้งที่จอง ยกเลิกทันเวลาได้คืน ระบบไม่มีแบบไม่จำกัด ถ้าต้องการให้จองได้ทุกวันตลอดปีให้ใส่ 365')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->required(),

                        TextInput::make('validity_days')
                            ->label('อายุ (วัน)')
                            ->helperText('นับจากวันที่ออกให้ลูกทีม — 90 = สามเดือน, 365 = หนึ่งปี')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->default(90)
                            ->required(),

                        TextInput::make('price')
                            ->label('ราคา')
                            ->prefix('฿')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('เปิดขาย')
                            ->helperText('ปิดไว้ = ไม่ขึ้นในรายการตอนออกแพ็กเกจให้ลูกทีม ใบที่ออกไปแล้วไม่กระทบ')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
