<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\MemberPackage;
use App\Models\Package;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * ออกแพ็กเกจให้ลูกทีม
 *
 * การจองทุกครั้งตัดเครดิตจากแพ็กเกจที่ยังใช้ได้และใกล้หมดอายุที่สุดก่อน
 * ไม่มีแพ็กเกจ = จองไม่ได้เลย หน้าจอนี้จึงเป็นทางเดียวที่ทำให้ลูกทีมเริ่มจองได้
 */
class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $title = 'แพ็กเกจ';

    protected static ?string $modelLabel = 'แพ็กเกจ';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('package_id')
                ->label('เลือกจากรายการที่ขาย')
                ->helperText('เลือกแล้วระบบเติมจำนวนครั้ง ราคา และวันหมดอายุให้ หรือข้ามไปกรอกเองด้านล่างก็ได้')
                ->options(fn () => Package::query()->active()->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    $package = Package::find($state);

                    if (! $package) {
                        return;
                    }

                    $set('package_name', $package->name);
                    $set('credits_total', $package->credits);
                    $set('price_paid', $package->price);
                    $set('expires_at', now()->addDays($package->validity_days)->toDateString());
                }),

            TextInput::make('package_name')
                ->label('ชื่อแพ็กเกจ')
                ->helperText('เก็บเป็นข้อความ ชื่อในรายการขายเปลี่ยนทีหลังก็ไม่กระทบใบนี้')
                ->required()
                ->maxLength(255),

            TextInput::make('credits_total')
                ->label('จำนวนครั้ง')
                ->numeric()
                ->minValue(1)
                ->maxValue(1000)
                ->required(),

            TextInput::make('credits_used')
                ->label('ใช้ไปแล้ว')
                ->helperText('ระบบนับให้เองทุกครั้งที่จอง แก้มือเฉพาะตอนต้องชดเชย')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            TextInput::make('price_paid')
                ->label('ยอดที่ชำระ')
                ->prefix('฿')
                ->numeric()
                ->minValue(0)
                ->default(0),

            DatePicker::make('starts_at')
                ->label('เริ่มใช้ได้')
                ->default(now())
                ->required(),

            DatePicker::make('expires_at')
                ->label('หมดอายุ')
                ->default(now()->addDays(90))
                ->after('starts_at')
                ->required(),

            Select::make('status')
                ->label('สถานะ')
                ->options([
                    MemberPackage::STATUS_ACTIVE => 'ใช้งานได้',
                    MemberPackage::STATUS_EXHAUSTED => 'ใช้ครบแล้ว',
                    MemberPackage::STATUS_EXPIRED => 'หมดอายุ',
                    MemberPackage::STATUS_CANCELLED => 'ยกเลิก',
                ])
                ->default(MemberPackage::STATUS_ACTIVE)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('package_name')
            ->defaultSort('expires_at')
            ->emptyStateHeading('ยังไม่มีแพ็กเกจ')
            ->emptyStateDescription('ลูกทีมที่ไม่มีแพ็กเกจจะจองรอบไม่ได้')
            ->columns([
                TextColumn::make('package_name')
                    ->label('แพ็กเกจ')
                    ->searchable(),

                TextColumn::make('credits_used')
                    ->label('ใช้ไป')
                    ->formatStateUsing(fn ($state, MemberPackage $record) => $state.' / '.$record->credits_total),

                TextColumn::make('credits_remaining')
                    ->label('คงเหลือ')
                    ->badge()
                    ->state(fn (MemberPackage $record) => $record->creditsRemaining())
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('expires_at')
                    ->label('หมดอายุ')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        MemberPackage::STATUS_ACTIVE => 'ใช้งานได้',
                        MemberPackage::STATUS_EXHAUSTED => 'ใช้ครบแล้ว',
                        MemberPackage::STATUS_EXPIRED => 'หมดอายุ',
                        MemberPackage::STATUS_CANCELLED => 'ยกเลิก',
                        default => $state,
                    })
                    ->color(fn ($state) => $state === MemberPackage::STATUS_ACTIVE ? 'success' : 'gray'),

                TextColumn::make('issuer.full_name')
                    ->label('ออกโดย')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('ออกแพ็กเกจ')
                    ->modalHeading('ออกแพ็กเกจให้ลูกทีม')
                    ->mutateDataUsing(function (array $data): array {
                        $data['issued_by'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('แก้ไข'),
                DeleteAction::make()->label('ลบ'),
            ]);
    }
}
