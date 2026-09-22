<?php

namespace App\Filament\Resources\MemberGroups\Schemas;

use App\Models\MemberGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ข้อมูลกลุ่ม')
                    ->description('ชื่อและคำอธิบายเป็นของเทรนเนอร์ แอดมินดูได้แต่ไม่ควรแก้แทน')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('ชื่อกลุ่ม')->disabled(),
                        TextInput::make('trainer.user.name')->label('เทรนเนอร์')->disabled(),
                        TextInput::make('description')->label('คำอธิบาย')->disabled()->columnSpanFull(),
                    ]),

                Section::make('เพดานจำนวนสมาชิก')
                    ->description('ส่วนนี้เป็นของแอดมิน เทรนเนอร์แก้ไม่ได้')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_members')
                            ->label('จำกัดเฉพาะกลุ่มนี้')
                            ->helperText(fn (?MemberGroup $record) => $record
                                ? 'เว้นว่างไว้ = ใช้ค่าของสาขา ('.$record->branch->max_group_size.' คน)'
                                : 'เว้นว่างไว้ = ใช้ค่าของสาขา')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->placeholder('ใช้ค่าของสาขา'),

                        Toggle::make('is_active')
                            ->label('เปิดใช้งาน')
                            ->helperText('ปิดแล้วเทรนเนอร์จะจองทั้งกลุ่มไม่ได้'),
                    ]),
            ]);
    }
}
