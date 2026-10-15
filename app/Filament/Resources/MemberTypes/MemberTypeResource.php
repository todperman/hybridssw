<?php

namespace App\Filament\Resources\MemberTypes;

use App\Filament\Resources\MemberTypes\Pages\ListMemberTypes;
use App\Models\MemberType;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * ประเภทสมาชิกที่ได้สิทธิ์ใช้ฟรี (บุคลากร รพ. แพทย์ ฯลฯ) และโควตาชั่วโมงต่อเดือนของแต่ละประเภท
 * การให้สิทธิ์รายคนทำที่หน้าสมาชิก ไม่ใช่ที่นี่
 */
class MemberTypeResource extends Resource
{
    protected static ?string $model = MemberType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'ตั้งค่าระบบ';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'ประเภทสมาชิก (สิทธิ์ฟรี)';

    protected static ?string $modelLabel = 'ประเภทสมาชิก';

    protected static ?string $pluralModelLabel = 'ประเภทสมาชิก';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('ชื่อประเภท')
                ->placeholder('เช่น บุคลากร รพ.')
                ->required()
                ->maxLength(80)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, ?MemberType $record) => $record ?: $set('code', Str::slug($state, '_') ?: Str::lower(Str::random(6)))),
            TextInput::make('code')
                ->label('รหัส (ใช้อ้างอิงในระบบ)')
                ->required()
                ->maxLength(30)
                ->alphaDash()
                ->unique(ignoreRecord: true),
            TextInput::make('description')
                ->label('คำอธิบาย')
                ->maxLength(255),
            TextInput::make('free_hours_per_month')
                ->label('โควตาฟรี (ชั่วโมงต่อเดือน)')
                ->helperText('เว้นว่าง = ไม่จำกัด เกินโควตาคิดราคาปกติสำหรับส่วนของคนนั้น')
                ->numeric()
                ->minValue(0)
                ->maxValue(500),
            TextInput::make('sort_order')
                ->label('ลำดับ')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->label('เปิดใช้')
                ->helperText('ปิดแล้วคนที่เป็นประเภทนี้อยู่จะไม่ได้สิทธิ์ฟรีจนกว่าจะเปิดอีกครั้ง')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('ประเภท')->weight('bold')->description(fn (MemberType $r) => $r->description),
                TextColumn::make('code')->label('รหัส')->badge()->color('gray'),
                TextColumn::make('free_hours_per_month')->label('โควตา')->formatStateUsing(fn (MemberType $r) => $r->quotaLabel())->placeholder('ฟรีไม่จำกัด'),
                TextColumn::make('members_count')->label('สมาชิก')->counts('members')->formatStateUsing(fn ($state) => $state.' คน'),
                IconColumn::make('is_active')->label('เปิดใช้')->boolean(),
            ])
            ->recordActions([EditAction::make()->slideOver()])
            ->headerActions([CreateAction::make()->label('เพิ่มประเภท')->slideOver()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberTypes::route('/'),
        ];
    }
}
