<?php

namespace App\Filament\Resources\Trainers\Schemas;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Models\Trainer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TrainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ข้อมูลเทรนเนอร์')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('บัญชีผู้ใช้')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

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

                Section::make('โควตาและสถานะ')
                    ->columns(3)
                    ->schema([
                        Select::make('status')
                            ->label('สถานะ')
                            ->options(TrainerStatus::class)
                            ->required(),

                        TextInput::make('max_seats_per_session')
                            ->label('ที่นั่งสูงสุดต่อรอบ')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->placeholder(fn (Get $get) => static::defaultHint($get('type'), 'seats')),

                        TextInput::make('advance_booking_days')
                            ->label('จองล่วงหน้าได้ (วัน)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->placeholder(fn (Get $get) => static::defaultHint($get('type'), 'days')),

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
                            ->rows(2)
                            ->columnSpan(2),
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
            'seats' => $type->defaultMaxSeatsPerSession(),
            'days' => $type->defaultAdvanceBookingDays(),
            'team' => $type->defaultMaxTeamSize(),
        };

        // ขนาดทีมเป็น null ได้ หมายถึงไม่จำกัด ถ้าปล่อยไปจะได้ข้อความห้อยว่า "ค่ามาตรฐาน "
        return 'ค่ามาตรฐาน '.($value ?? 'ไม่จำกัด');
    }
}
