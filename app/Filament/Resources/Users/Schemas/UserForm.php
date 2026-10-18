<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\ThaiPhone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * บัญชีผู้ใช้ (ทุกบทบาท) คอลัมน์หลักคือข้อมูลบัญชีและรหัสผ่าน คอลัมน์ข้างคือสถานะและข้อมูลระบบ
 * รหัสผ่านบังคับเฉพาะตอนสร้าง ตอนแก้เว้นว่าง = ไม่เปลี่ยน (โมเดลแฮชให้เอง)
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        Section::make('ข้อมูลบัญชี')
                            ->icon('heroicon-o-user')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('first_name')->label('ชื่อ')->required()->maxLength(120),
                                TextInput::make('last_name')->label('นามสกุล')->required()->maxLength(120),
                                TextInput::make('nickname')->label('ชื่อเล่น')->maxLength(60),
                                TextInput::make('phone')
                                    ->label('เบอร์โทร')
                                    ->tel()
                                    ->rules([new ThaiPhone])
                                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? ThaiPhone::digits($state) : null)
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
                                Select::make('role')
                                    ->label('บทบาท')
                                    ->options(UserRole::class)
                                    ->default('member')
                                    ->required()
                                    ->helperText('สมาชิกและเทรนเนอร์ควรสมัครผ่านหน้าเว็บ บทบาทนี้ใช้สร้างแอดมิน/เจ้าหน้าที่'),
                                Select::make('branch_id')
                                    ->label('สาขา')
                                    ->relationship('branch', 'name')
                                    ->placeholder('ทุกสาขา')
                                    ->helperText('เจ้าหน้าที่ที่ผูกสาขาจะเห็นเฉพาะข้อมูลสาขานั้น'),
                            ]),

                        Section::make('รหัสผ่าน')
                            ->icon('heroicon-o-key')
                            ->schema([
                                TextInput::make('password')
                                    ->label(fn (string $operation) => $operation === 'create' ? 'รหัสผ่าน' : 'ตั้งรหัสผ่านใหม่')
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->dehydrated(fn (?string $state) => filled($state))
                                    ->minLength(8)
                                    ->maxLength(100)
                                    ->helperText(fn (string $operation) => $operation === 'create' ? 'อย่างน้อย 8 ตัว แจ้งผู้ใช้ให้เปลี่ยนหลังเข้าครั้งแรก' : 'เว้นว่าง = ไม่เปลี่ยนรหัสผ่าน'),
                            ]),
                    ]),

                Group::make()
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                        Section::make('สถานะ')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('ใช้งานได้')
                                    ->default(true)
                                    ->helperText('ปิด = เข้าสู่ระบบไม่ได้ ข้อมูลยังอยู่'),
                            ]),

                        Section::make('ข้อมูลระบบ')
                            ->icon('heroicon-o-information-circle')
                            ->visibleOn('edit')
                            ->schema([
                                TextEntry::make('created_at')->label('สร้างเมื่อ')->dateTime('d/m/Y H:i'),
                                TextEntry::make('last_login_at')->label('เข้าใช้ล่าสุด')->dateTime('d/m/Y H:i')->placeholder('ยังไม่เคย'),
                                TextEntry::make('email_verified_at')->label('ยืนยันอีเมล')->dateTime('d/m/Y H:i')->placeholder('ยังไม่ยืนยัน'),
                                TextEntry::make('line_user_id')->label('LINE ID')->placeholder('ไม่ได้เชื่อม'),
                            ]),
                    ]),
            ]);
    }
}
