<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Rules\ThaiPhone;
use App\Support\PriceTable;
use Filament\Forms\Components\Repeater;
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

                Section::make('ราคา')
                    ->description('บาทต่อชั่วโมง รวมทั้งกลุ่ม ตามจำนวนผู้เข้าร่วม (ไม่นับ Trainer) เกินจำนวนที่ตั้งไว้คิดเพิ่มต่อคน')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        static::priceColumn(PriceTable::WITH_TRAINER, 'แบบมี Trainer'),
                        static::priceColumn(PriceTable::WITHOUT_TRAINER, 'แบบไม่มี Trainer'),
                    ]),

                Section::make('ส่วนแบ่งเทรนเนอร์')
                    ->description('แยกราคาแบบมี Trainer ต่อชั่วโมงเป็น "ค่า Trainer" กับ "ค่า Facility" ตามจำนวนคน ทั้งสองช่องต้องรวมกันเท่าราคา · เทรนเนอร์ภายในได้ 0 · สิทธิ์ฟรี/ส่วนลดไม่ลดส่วนแบ่ง')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('payout.tiers')
                            ->label('ค่า Trainer / ค่า Facility ต่อชั่วโมง ตามจำนวนคน')
                            ->columns(2)
                            ->schema([
                                TextInput::make('trainer')->label('ค่า Trainer')->numeric()->minValue(0)->suffix('บาท/ชม.')->required(),
                                TextInput::make('facility')->label('ค่า Facility')->numeric()->minValue(0)->suffix('บาท/ชม.')->required(),
                            ])
                            ->itemLabel(fn (?int $index) => ($index === null ? '' : ($index + 1).' คน'))
                            ->addActionLabel('เพิ่มจำนวนคนถัดไป')
                            ->reorderable(false)
                            ->minItems(1)
                            ->maxItems(20)
                            ->default(config('gym.payout.tiers', []))
                            // สาขาเก่าที่ยังไม่เคยตั้ง: โชว์ค่าตั้งต้นให้แก้ต่อ ไม่ใช่ฟอร์มว่าง
                            ->afterStateHydrated(fn (Repeater $component, $state) => blank($state) ? $component->state(config('gym.payout.tiers', [])) : null),
                        TextInput::make('payout.extra.trainer')
                            ->label('คนที่เกินตาราง: ค่า Trainer เพิ่มคนละ')
                            ->numeric()->minValue(0)->suffix('บาท/ชม.')->required()
                            ->default(config('gym.payout.extra.trainer', 0))
                            ->afterStateHydrated(fn (TextInput $component, $state) => blank($state) ? $component->state(config('gym.payout.extra.trainer', 0)) : null),
                        TextInput::make('payout.extra.facility')
                            ->label('คนที่เกินตาราง: ค่า Facility เพิ่มคนละ')
                            ->numeric()->minValue(0)->suffix('บาท/ชม.')->required()
                            ->default(config('gym.payout.extra.facility', 0))
                            ->afterStateHydrated(fn (TextInput $component, $state) => blank($state) ? $component->state(config('gym.payout.extra.facility', 0)) : null),
                        \Filament\Schemas\Components\Text::make(fn (\Filament\Schemas\Components\Utilities\Get $get) => static::payoutWarning($get))
                            ->color('danger')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => static::payoutWarning($get) !== '')
                            ->columnSpanFull(),
                    ]),

                Section::make('การจองยิม')
                    ->description('หนึ่งการจองได้ใช้ทั้งยิม ตั้งจำนวนคน ชั่วโมง และระยะจองล่วงหน้า')
                    ->columns(2)
                    ->schema([
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

    /** เตือนทันทีเมื่อ Trainer + Facility ไม่เท่าราคาแบบมี Trainer (ตรวจตอนพิมพ์ ไม่กันบันทึก เพราะราคาอาจกำลังแก้พร้อมกัน) */
    protected static function payoutWarning(\Filament\Schemas\Components\Utilities\Get $get): string
    {
        $pricing = $get('pricing') ?: [];
        $tiers = $pricing[PriceTable::WITH_TRAINER]['tiers'] ?? [];
        $tiers = is_array($tiers) && $tiers !== [] && is_array(reset($tiers)) ? array_map(fn ($row) => (float) ($row['price'] ?? 0), array_values($tiers)) : (array) $tiers;
        $prices = PriceTable::fromArray([PriceTable::WITH_TRAINER => ['tiers' => $tiers, 'extra_per_person' => $pricing[PriceTable::WITH_TRAINER]['extra_per_person'] ?? 0], PriceTable::WITHOUT_TRAINER => []]);
        $payout = \App\Support\PayoutTable::fromArray($get('payout') ?: []);

        $problems = $payout->mismatches($prices);

        return $problems ? 'ยังไม่ตรงกับราคา: '.implode(' · ', $problems) : '';
    }

    /** ตารางราคาหนึ่งแบบ: แถวละจำนวนคน ใส่ราคารวมต่อชั่วโมง แล้วมีช่องราคาต่อคนที่เกินจากตาราง */
    protected static function priceColumn(string $mode, string $title): Section
    {
        $defaults = config("gym.pricing.{$mode}");

        return Section::make($title)
            ->schema([
                Repeater::make("pricing.{$mode}.tiers")
                    ->label('ราคารวมต่อชั่วโมง ตามจำนวนคน')
                    ->schema([
                        TextInput::make('price')
                            ->hiddenLabel()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('บาท/ชม.')
                            ->required(),
                    ])
                    ->itemLabel(fn (?int $index) => ($index === null ? '' : ($index + 1).' คน'))
                    ->addActionLabel('เพิ่มจำนวนคนถัดไป')
                    ->reorderable(false)
                    ->minItems(1)
                    ->maxItems(20)
                    ->default(array_map(fn ($p) => ['price' => $p], $defaults['tiers'] ?? []))
                    // เก็บเป็นรายการตัวเลขล้วน ไม่ใช่ [{price: ...}] ให้ PriceTable อ่านตรง ๆ ได้
                    ->afterStateHydrated(function (Repeater $component, $state) {
                        if (is_array($state) && $state !== [] && ! is_array(reset($state))) {
                            $component->state(array_map(fn ($p) => ['price' => $p], array_values($state)));
                        }
                    })
                    ->dehydrateStateUsing(fn ($state) => array_values(array_map(fn ($row) => (float) ($row['price'] ?? 0), $state ?? []))),
                TextInput::make("pricing.{$mode}.extra_per_person")
                    ->label('คนถัดไปที่เกินจากตาราง คิดเพิ่มคนละ')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('บาท/ชม.')
                    ->required()
                    ->default($defaults['extra_per_person'] ?? 0),
            ]);
    }
}
