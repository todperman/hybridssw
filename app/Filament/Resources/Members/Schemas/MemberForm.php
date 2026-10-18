<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\Trainer;
use App\Models\User;
use App\Rules\ThaiPhone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * แก้ไขสมาชิกหนึ่งคน รวมข้อมูลติดต่อที่อยู่บนบัญชีผู้ใช้ (ชื่อ เบอร์ อีเมล) ไว้หน้าเดียว
 * เจ้าหน้าที่จะได้ไม่ต้องไปหาที่หน้า "ผู้ใช้" อีกหน้า
 */
class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ข้อมูลติดต่อ')
                    ->description('ข้อมูลเดียวกับที่สมาชิกกรอกตอนสมัคร แก้ที่นี่มีผลกับบัญชีเข้าสู่ระบบด้วย')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Group::make()
                            ->relationship('user')
                            ->columnSpanFull()
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('first_name')->label('ชื่อ')->required()->maxLength(120),
                                TextInput::make('last_name')->label('นามสกุล')->required()->maxLength(120),
                                TextInput::make('nickname')->label('ชื่อเล่น')->maxLength(60),
                                TextInput::make('phone')
                                    ->label('เบอร์โทร')
                                    ->tel()
                                    ->required()
                                    ->rules([new ThaiPhone])
                                    ->dehydrateStateUsing(fn (?string $state) => ThaiPhone::digits($state))
                                    ->unique(table: User::class, column: 'phone', ignoreRecord: true)
                                    ->validationMessages(['unique' => 'เบอร์นี้มีบัญชีอื่นใช้อยู่แล้ว']),
                                TextInput::make('email')
                                    ->label('อีเมล')
                                    ->email()
                                    ->required()
                                    ->maxLength(190)
                                    ->unique(table: User::class, column: 'email', ignoreRecord: true)
                                    ->validationMessages(['unique' => 'อีเมลนี้มีบัญชีอื่นใช้อยู่แล้ว'])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('ข้อมูลสมาชิก')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('member_code')
                            ->label('รหัสสมาชิก')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('ระบบสร้างให้อัตโนมัติ'),
                        Select::make('branch_id')
                            ->label('สาขา')
                            ->relationship('branch', 'name')
                            ->required(),
                        Select::make('primary_trainer_id')
                            ->label('เทรนเนอร์ประจำ')
                            ->options(fn () => Trainer::query()->with('user')->get()->mapWithKeys(fn (Trainer $t) => [$t->id => ($t->user?->name ?? '-').' ('.$t->code.')'])->all())
                            ->searchable()
                            ->placeholder('ยังไม่มี'),
                        DatePicker::make('date_of_birth')
                            ->label('วันเกิด')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()),
                        Select::make('gender')
                            ->label('เพศ')
                            ->options(Member::GENDERS)
                            ->placeholder('ไม่ระบุ'),
                        TextInput::make('emergency_contact_name')
                            ->label('ผู้ติดต่อฉุกเฉิน')
                            ->maxLength(120),
                        TextInput::make('emergency_contact_phone')
                            ->label('เบอร์ผู้ติดต่อฉุกเฉิน')
                            ->tel()
                            ->rules([ThaiPhone::anyLine()])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? ThaiPhone::digits($state) : null),
                        Textarea::make('health_note')
                            ->label('ข้อมูลสุขภาพ / ข้อควรระวัง')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('benefit_claim')
                            ->label('แจ้งตอนสมัครว่าเป็น')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('ไม่ได้แจ้ง')
                            ->helperText('กำหนดประเภทสมาชิก (สิทธิ์ฟรี) ได้จากปุ่ม "ประเภทสมาชิก" ในตาราง')
                            ->columnSpanFull(),
                    ]),

                Section::make('สถานะ')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('status')
                            ->label('สถานะ')
                            ->options(MemberStatus::class)
                            ->required()
                            ->helperText('อนุมัติ/ไม่อนุมัติการสมัครให้ใช้ปุ่มในตาราง เพื่อให้สมาชิกได้รับแจ้งและมีประวัติ'),
                        DateTimePicker::make('suspended_until')
                            ->label('ระงับถึง')
                            ->native(false)
                            ->seconds(false),
                        Textarea::make('suspension_reason')
                            ->label('เหตุผลที่ระงับ')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
