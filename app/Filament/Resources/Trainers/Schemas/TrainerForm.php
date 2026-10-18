<?php

namespace App\Filament\Resources\Trainers\Schemas;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Models\Trainer;
use App\Models\User;
use App\Rules\ThaiPhone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TrainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                // ข้อมูลติดต่ออยู่บนบัญชีผู้ใช้ แก้ที่นี่ได้เลยไม่ต้องไปหน้า "ผู้ใช้" (เฉพาะตอนแก้ไข ตอนสร้างเลือกบัญชีก่อน)
                Section::make('ข้อมูลติดต่อ')
                    ->description('ข้อมูลเดียวกับที่เทรนเนอร์กรอกตอนสมัคร แก้ที่นี่มีผลกับบัญชีเข้าสู่ระบบด้วย')
                    ->visibleOn('edit')
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

                Section::make('ข้อมูลเทรนเนอร์')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('บัญชีผู้ใช้')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->hiddenOn('edit'),

                        Select::make('branch_id')
                            ->label('สาขา')
                            ->relationship('branch', 'name')
                            ->required(),

                        TextInput::make('code')
                            ->label('รหัสเทรนเนอร์')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('ระบบสร้างให้อัตโนมัติ'),

                        Select::make('type')
                            ->label('ประเภท')
                            ->options(TrainerType::class)
                            ->required()
                            ->live()
                            ->helperText('ภายนอกต้องแนบใบรับรองและให้แอดมินอนุมัติก่อนจึงจะจองได้'),

                        Select::make('gender')
                            ->label('เพศ')
                            ->options(\App\Enums\Gender::options())
                            ->placeholder('ไม่ระบุ'),

                        Textarea::make('bio')
                            ->label('แนะนำตัว')
                            ->rows(3)
                            ->columnSpanFull(),

                        TagsInput::make('specialties')
                            ->label('ความถนัด')
                            ->placeholder('เพิ่มแล้วกด Enter')
                            ->columnSpanFull(),
                    ]),

                Section::make('เอกสารและสัญญา')
                    ->description('บังคับเฉพาะเทรนเนอร์ภายนอก')
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('type') === TrainerType::External->value)
                    ->schema([
                        TextInput::make('certification_name')
                            ->label('ชื่อใบรับรอง')
                            ->required(fn (Get $get) => $get('type') === TrainerType::External->value),

                        DatePicker::make('certification_expires_at')
                            ->label('ใบรับรองหมดอายุ')
                            ->helperText('เลยวันนี้แล้วระบบจะบล็อกการจองทันที'),

                        FileUpload::make('certification_file_path')
                            ->label('ไฟล์ใบรับรอง')
                            ->directory('certifications')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120)
                            ->columnSpanFull(),

                        DatePicker::make('contract_starts_at')->label('สัญญาเริ่ม'),
                        DatePicker::make('contract_ends_at')->label('สัญญาสิ้นสุด'),
                    ]),

                    ]),

                Group::make()
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->schema([
                Section::make('สถานะและทีม')
                    ->schema([
                        Select::make('status')
                            ->label('สถานะ')
                            ->options(TrainerStatus::class)
                            ->required(),

                        Toggle::make('accepts_bookings')
                            ->label('เปิดรับงานจอง')
                            ->helperText('ปิดไว้ = ลูกเทรนเลือกคนนี้ไม่ได้ งานที่มีอยู่ยังอยู่')
                            ->default(true)
                            ->inline(false),

                        // เก็บลงคอลัมน์เดียวแต่มีสามความหมาย จึงแยกเป็นตัวเลือกโหมด
                        // แล้วค่อยแปลงเป็นค่าจริงตอนบันทึก ดูที่ EditTrainer / CreateTrainer
                        Select::make('team_size_mode')
                            ->label('ขนาดทีมสูงสุด')
                            ->options([
                                'default' => 'ใช้ค่ามาตรฐานตามประเภท',
                                'unlimited' => 'ไม่จำกัดจำนวน',
                                'custom' => 'กำหนดเอง',
                            ])
                            ->default('default')
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateHydrated(function (Select $component, ?Trainer $record): void {
                                $component->state(match (true) {
                                    $record?->max_team_size === null => 'default',
                                    (int) $record->max_team_size === 0 => 'unlimited',
                                    default => 'custom',
                                });
                            })
                            ->helperText(fn (Get $get) => match ($get('team_size_mode')) {
                                'unlimited' => 'เทรนเนอร์คนนี้เพิ่มลูกทีมได้ไม่จำกัด แม้ประเภทจะมีเพดานก็ตาม',
                                'custom' => null,
                                default => static::defaultHint($get('type'), 'team'),
                            }),

                        TextInput::make('max_team_size')
                            ->label('จำนวนลูกทีมสูงสุด')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required()
                            ->visible(fn (Get $get) => $get('team_size_mode') === 'custom'),

                        Textarea::make('review_note')
                            ->label('บันทึกการตรวจสอบ')
                            ->rows(2),
                    ]),

                Section::make('ข้อมูลระบบ')
                    ->visibleOn('edit')
                    ->schema([
                        TextEntry::make('code')->label('รหัสเทรนเนอร์')->badge()->color('gray')->copyable(),
                        TextEntry::make('created_at')->label('สมัครเมื่อ')->dateTime('d/m/Y H:i'),
                        TextEntry::make('approved_at')->label('อนุมัติเมื่อ')->dateTime('d/m/Y H:i')->placeholder('ยังไม่อนุมัติ')
                            ->helperText(fn (Trainer $record) => $record->approver?->name ? 'โดย '.$record->approver->name : null),
                        TextEntry::make('team_members_count')->label('ลูกทีม')->state(fn (Trainer $record) => $record->teamMembers()->count().' คน'),
                    ]),
                    ]),
            ]);
    }

    /**
     * แปลงโหมดที่เลือกให้เป็นค่าจริงของคอลัมน์ max_team_size
     *
     * เรียกจากหน้าสร้างและหน้าแก้ไข ไม่งั้นค่าที่ส่งมาจะเป็นชื่อโหมดซึ่งเก็บลงคอลัมน์ไม่ได้
     */
    public static function resolveTeamSize(array $data): array
    {
        $mode = $data['team_size_mode'] ?? 'default';
        unset($data['team_size_mode']);

        $data['max_team_size'] = match ($mode) {
            'unlimited' => 0,
            'custom' => $data['max_team_size'] !== null && $data['max_team_size'] !== ''
                ? (int) $data['max_team_size']
                : null,
            default => null,
        };

        return $data;
    }

    /**
     * เว้นว่างไว้ = ใช้ค่ามาตรฐานตามประเภทเทรนเนอร์ แสดงให้เห็นว่าค่านั้นคือเท่าไร
     *
     * ต้องรับได้ทั้ง enum และสตริง เพราะหน้าแก้ไขอ่านค่าจากโมเดลที่ cast เป็น enum แล้ว
     * ส่วนหน้าสร้างอ่านจาก Select ซึ่งยังเป็นสตริงอยู่
     */
    protected static function defaultHint(TrainerType|string|null $type, string $key): string
    {
        $type = $type instanceof TrainerType
            ? $type
            : (TrainerType::tryFrom((string) $type) ?? TrainerType::External);

        $value = match ($key) {
            'team' => $type->defaultMaxTeamSize(),
        };

        // ขนาดทีมเป็น null ได้ หมายถึงไม่จำกัด ถ้าปล่อยไปจะได้ข้อความห้อยว่า "ค่ามาตรฐาน "
        return 'ค่ามาตรฐาน '.($value ?? 'ไม่จำกัด');
    }
}
