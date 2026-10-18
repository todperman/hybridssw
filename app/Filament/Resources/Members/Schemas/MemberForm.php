<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\Gender;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * แก้ไขสมาชิกหนึ่งคน: คอลัมน์หลักคือข้อมูลติดต่อ (อยู่บนบัญชีผู้ใช้) และข้อมูลสมาชิก
 * คอลัมน์ข้างคือสถานะและข้อมูลระบบที่อ่านอย่างเดียว เลย์เอาต์เดียวกับหน้าเทรนเนอร์และผู้ใช้
 */
class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        Section::make('ข้อมูลติดต่อ')
                            ->description('ข้อมูลเดียวกับที่สมาชิกกรอกตอนสมัคร แก้ที่นี่มีผลกับบัญชีเข้าสู่ระบบด้วย')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Group::make()
                                    ->relationship('user')
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
                            ->icon('heroicon-o-identification')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
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
                                    ->options(Gender::options())
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
                            ]),
                    ]),

                Group::make()
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        Section::make('สถานะ')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Select::make('status')
                                    ->label('สถานะ')
                                    ->options(MemberStatus::class)
                                    ->required()
                                    ->helperText('อนุมัติ/ไม่อนุมัติการสมัครให้ใช้ปุ่มในตาราง สมาชิกจะได้รับแจ้งและมีประวัติ'),
                                DateTimePicker::make('suspended_until')
                                    ->label('ระงับถึง')
                                    ->native(false)
                                    ->seconds(false),
                                Textarea::make('suspension_reason')
                                    ->label('เหตุผลที่ระงับ')
                                    ->rows(2),
                            ]),

                        Section::make('ข้อมูลระบบ')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                TextEntry::make('member_code')->label('รหัสสมาชิก')->badge()->color('gray')->copyable(),
                                TextEntry::make('memberType.name')->label('ประเภทสมาชิก (สิทธิ์ฟรี)')->placeholder('สมาชิกทั่วไป')
                                    ->helperText('เปลี่ยนได้จากปุ่ม "ประเภทสมาชิก" ในตาราง'),
                                TextEntry::make('benefit_claim')->label('แจ้งตอนสมัครว่าเป็น')->placeholder('ไม่ได้แจ้ง'),
                                TextEntry::make('created_at')->label('สมัครเมื่อ')->dateTime('d/m/Y H:i'),
                                TextEntry::make('approved_at')->label('อนุมัติเมื่อ')->dateTime('d/m/Y H:i')->placeholder('ยังไม่อนุมัติ')
                                    ->helperText(fn (Member $record) => $record->approvedBy?->name ? 'โดย '.$record->approvedBy->name : null),
                                TextEntry::make('review_note')->label('เหตุผลที่ไม่อนุมัติ')->placeholder('—')->visible(fn (Member $record) => filled($record->review_note)),
                            ]),
                    ]),
            ]);
    }
}
