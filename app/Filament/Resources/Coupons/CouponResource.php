<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * คูปองส่วนลด แอดมินสร้าง ลูกค้ากรอกโค้ดตอนจอง
 * จำนวนครั้งที่ใช้นับจากการจองที่ยังถือเวลาอยู่ รายการที่หมดอายุหรือยกเลิกคืนสิทธิ์เอง
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'คูปองส่วนลด';

    protected static ?string $modelLabel = 'คูปอง';

    protected static ?string $pluralModelLabel = 'คูปองส่วนลด';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('โค้ด')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('โค้ด')
                        ->placeholder('เช่น WELCOME10')
                        ->helperText('ตัวพิมพ์ใหญ่ ไม่มีช่องว่าง ลูกค้าพิมพ์เล็กหรือใหญ่ก็ใช้ได้')
                        ->required()
                        ->maxLength(40)
                        // ตรวจกับโค้ดที่แปลงเป็นตัวพิมพ์ใหญ่ไม่มีช่องว่างแล้ว เหมือนที่จะถูกบันทึกจริง
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! preg_match('/^[A-Z0-9_-]+$/', Coupon::normalize($value))) {
                                $fail('โค้ดใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข ขีดกลาง และขีดล่าง');
                            }
                        })
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $state) => $rule->where('code', Coupon::normalize($state)))
                        ->dehydrateStateUsing(fn ($state) => Coupon::normalize($state))
                        ->suffixAction(\Filament\Actions\Action::make('random')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('สุ่มโค้ด')
                            ->action(fn (callable $set) => $set('code', 'HS'.strtoupper(Str::random(6))))),
                    TextInput::make('name')
                        ->label('ชื่อแคมเปญ')
                        ->placeholder('เช่น ต้อนรับสมาชิกใหม่')
                        ->required()
                        ->maxLength(120),
                    Textarea::make('description')
                        ->label('หมายเหตุ (แอดมินเห็นคนเดียว)')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('ส่วนลด')
                ->columns(['default' => 1, 'sm' => 3])
                ->schema([
                    Select::make('type')
                        ->label('แบบ')
                        ->options([Coupon::TYPE_PERCENT => 'เปอร์เซ็นต์', Coupon::TYPE_FIXED => 'จำนวนเงิน (บาท)'])
                        ->default(Coupon::TYPE_PERCENT)
                        ->required()
                        ->live(),
                    TextInput::make('value')
                        ->label(fn (Get $get) => $get('type') === Coupon::TYPE_FIXED ? 'ลด (บาท)' : 'ลด (%)')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(fn (Get $get) => $get('type') === Coupon::TYPE_FIXED ? 1000000 : 100)
                        ->required(),
                    TextInput::make('max_discount')
                        ->label('ลดได้สูงสุด (บาท)')
                        ->helperText('ใช้กับแบบเปอร์เซ็นต์ เว้นว่าง = ไม่จำกัด')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Get $get) => $get('type') === Coupon::TYPE_PERCENT),
                    TextInput::make('min_amount')
                        ->label('ยอดขั้นต่ำ (บาท)')
                        ->helperText('ยอดหลังหักสิทธิ์ฟรี ก่อนหักคูปอง')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Select::make('applies_to')
                        ->label('ใช้กับ')
                        ->options([
                            Coupon::APPLIES_BOTH => 'ทุกแบบ',
                            Coupon::APPLIES_WITH_TRAINER => 'เฉพาะมี Trainer',
                            Coupon::APPLIES_WITHOUT_TRAINER => 'เฉพาะไม่มี Trainer',
                        ])
                        ->default(Coupon::APPLIES_BOTH)
                        ->required(),
                    Select::make('branch_id')
                        ->label('สาขา')
                        ->relationship('branch', 'name')
                        ->placeholder('ทุกสาขา'),
                ]),

            Section::make('ช่วงเวลาและจำนวนครั้ง')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    DateTimePicker::make('starts_at')->label('เริ่มใช้ได้')->native(false)->seconds(false)->placeholder('ทันที'),
                    DateTimePicker::make('expires_at')->label('หมดอายุ')->native(false)->seconds(false)->placeholder('ไม่หมดอายุ')->after('starts_at'),
                    TextInput::make('max_uses')->label('ใช้ได้ทั้งหมด (ครั้ง)')->helperText('เว้นว่าง = ไม่จำกัด')->numeric()->minValue(1),
                    TextInput::make('max_uses_per_member')->label('ต่อคน (ครั้ง)')->helperText('เว้นว่าง = ไม่จำกัด')->numeric()->minValue(1),
                    Toggle::make('is_active')->label('เปิดใช้')->default(true)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('branch'))
            ->columns([
                TextColumn::make('code')->label('โค้ด')->badge()->color('gray')->copyable()->copyMessage('คัดลอกโค้ดแล้ว')->searchable(),
                TextColumn::make('name')->label('แคมเปญ')->searchable()->description(fn (Coupon $r) => $r->appliesToLabel().($r->branch ? ' · '.$r->branch->name : '')),
                TextColumn::make('value')->label('ส่วนลด')->formatStateUsing(fn (Coupon $r) => $r->discountLabel())
                    ->description(fn (Coupon $r) => (float) $r->min_amount > 0 ? 'ขั้นต่ำ ฿'.number_format((float) $r->min_amount, 0) : null),
                TextColumn::make('uses')->label('ใช้แล้ว')
                    ->state(fn (Coupon $r) => $r->usedCount().($r->max_uses ? ' / '.$r->max_uses : ''))
                    ->description(fn (Coupon $r) => $r->max_uses_per_member ? 'คนละไม่เกิน '.$r->max_uses_per_member : null),
                TextColumn::make('expires_at')->label('หมดอายุ')->dateTime('d/m/Y H:i')->placeholder('ไม่หมดอายุ')->sortable()
                    ->color(fn (Coupon $r) => $r->expires_at?->isPast() ? 'danger' : null),
                IconColumn::make('is_active')->label('เปิดใช้')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('เปิดใช้'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
