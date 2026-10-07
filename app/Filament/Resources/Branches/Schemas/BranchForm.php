<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Rules\ThaiPhone;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ข้อมูลสาขา')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')->label('รหัสสาขา')->required(),
                        TextInput::make('name')->label('ชื่อสาขา')->required(),
                        TextInput::make('phone')->label('เบอร์โทร')->rules([ThaiPhone::anyLine()])->tel(),
                        TextInput::make('address')->label('ที่อยู่'),
                        TextInput::make('timezone')->label('เขตเวลา')->required()->default('Asia/Bangkok'),
                        Toggle::make('is_active')->label('เปิดใช้งาน')->default(true),
                    ]),

                Section::make('การจองยิม')
                    ->description('หนึ่งการจองได้ใช้ทั้งยิม ราคาคิดต่อชั่วโมงต่อการจอง ไม่ใช่ต่อคน ยังไม่ตั้งราคา = ยังเปิดจองไม่ได้')
                    ->columns(2)
                    ->schema([
                        TextInput::make('hourly_rate')
                            ->label('ราคาต่อชั่วโมง (บาท)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(0),
                        TextInput::make('max_trainees')
                            ->label('ผู้เข้าร่วมสูงสุดต่อการจอง')
                            ->helperText('ไม่นับ Trainer')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required()
                            ->default(6),
                        TextInput::make('booking_window_days')
                            ->label('จองล่วงหน้าได้ (วัน)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->default(45),
                        TextInput::make('max_booking_hours')
                            ->label('ชั่วโมงสูงสุดต่อการจอง')
                            ->helperText('จองหลายชั่วโมงต่อเนื่องกันได้ไม่เกินค่านี้')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->required()
                            ->default(4),
                        Textarea::make('payment_instructions')
                            ->label('วิธีชำระเงิน (ระหว่างที่ยังไม่เปิด Omise)')
                            ->helperText('ผู้ชำระเงินเห็นข้อความนี้ที่หน้าการจอง เช่น เลขบัญชีและช่องทางส่งสลิป แอดมินกดบันทึกรับชำระเมื่อตรวจแล้ว')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('ทีมของ Trainer')
                    ->schema([
                        TextInput::make('max_group_size')
                            ->label('จำนวนลูกทีมสูงสุดต่อกลุ่ม')
                            ->helperText('Trainer สร้างกลุ่มลูกทีมไว้เลือกคนตอนจองได้ จำนวนคนต่อกลุ่มถูกจำกัดด้วยค่านี้')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required()
                            ->default(5),
                    ]),
            ]);
    }
}
