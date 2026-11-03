<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\PayoutSettings as Settings;
use App\Support\PayoutTable;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * ตั้งค่าส่วนแบ่งเทรนเนอร์ที่เดียว: ตารางต่อสาขา (เลือกสาขาได้) และนโยบายรอบ ๆ
 * ตัวเลขเก็บที่ branches.payout นโยบายเก็บที่ app_settings[payout] ทุกการบันทึกลงประวัติ
 */
class PayoutSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'ตั้งค่าระบบ';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'ส่วนแบ่งเทรนเนอร์';

    protected static ?string $title = 'ตั้งค่าส่วนแบ่งเทรนเนอร์';

    protected static ?string $slug = 'payout-settings';

    protected string $view = 'filament.pages.payout-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $branch = Branch::query()->where('is_active', true)->orderBy('name')->first();
        $this->fillFor($branch?->id, Settings::load());
    }

    protected function fillFor(?int $branchId, array $policy): void
    {
        $branch = $branchId ? Branch::find($branchId) : null;
        $table = $branch?->payoutTable()->toArray() ?? config('gym.payout');

        $this->form->fill([
            'branch_id' => $branchId,
            'tiers' => $table['tiers'],
            'extra_trainer' => $table['extra']['trainer'],
            'extra_facility' => $table['extra']['facility'],
        ] + $policy);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ตารางส่วนแบ่งต่อชั่วโมง')
                    ->description('ราคาแบบมี Trainer แยกเป็นค่า Trainer กับค่า Facility ตามจำนวนคน ทั้งสองช่องต้องรวมเท่าราคาของสาขา')
                    ->schema([
                        Select::make('branch_id')
                            ->label('สาขา')
                            ->options(Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->fillFor((int) $state, $this->form->getRawState() + Settings::load()))
                            ->required(),
                        Repeater::make('tiers')
                            ->label('ตามจำนวนคน')
                            ->columns(3)
                            ->schema([
                                TextInput::make('trainer')->label('ค่า Trainer')->numeric()->minValue(0)->suffix('฿/ชม.')->required()->live(onBlur: true),
                                TextInput::make('facility')->label('ค่า Facility')->numeric()->minValue(0)->suffix('฿/ชม.')->required()->live(onBlur: true),
                                Text::make(fn (Get $get) => 'รวม ฿'.number_format((float) $get('trainer') + (float) $get('facility')))->color('gray'),
                            ])
                            ->itemLabel(fn (?int $index) => $index === null ? '' : ($index + 1).' คน')
                            ->addActionLabel('เพิ่มจำนวนคนถัดไป')
                            ->reorderable(false)
                            ->minItems(1)
                            ->maxItems(20),
                        TextInput::make('extra_trainer')->label('คนที่เกินตาราง: Trainer เพิ่มคนละ')->numeric()->minValue(0)->suffix('฿/ชม.')->required(),
                        TextInput::make('extra_facility')->label('คนที่เกินตาราง: Facility เพิ่มคนละ')->numeric()->minValue(0)->suffix('฿/ชม.')->required(),
                        Text::make(fn (Get $get) => $this->warning($get))
                            ->color('danger')
                            ->visible(fn (Get $get) => $this->warning($get) !== ''),
                    ])
                    ->columns(['default' => 1, 'lg' => 2]),

                Section::make('นโยบาย')
                    ->description('ใช้กับทุกสาขา มีผลกับงานที่ยืนยันหลังจากนี้ งานเก่าคงยอดเดิม')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Radio::make('internal')->label('เทรนเนอร์ภายใน (พนักงาน)')->options(Settings::INTERNAL)->required(),
                        Radio::make('discounts')->label('เมื่อลูกค้าใช้สิทธิ์ฟรีหรือโค้ดส่วนลด')->options(Settings::DISCOUNTS)->required(),
                        Radio::make('earn_when')->label('นับเป็นรายได้เมื่อ')->options(Settings::EARN_WHEN)->required(),
                        Select::make('cycle')->label('รอบจ่าย')->options(Settings::CYCLES)->selectablePlaceholder(false)->required(),
                        TextInput::make('cycle_note')->label('ข้อความบอกเทรนเนอร์เรื่องรอบจ่าย')->placeholder('เช่น โอนภายในวันที่ 5 ของเดือนถัดไป')->maxLength(120),
                        Toggle::make('visible')->label('แสดงตารางส่วนแบ่งและนโยบายในหน้า "รายได้" ของเทรนเนอร์')->inline(false),
                    ]),
            ]);
    }

    protected function warning(Get $get): string
    {
        $branch = $get('branch_id') ? Branch::find($get('branch_id')) : null;

        if (! $branch) {
            return '';
        }

        $payout = PayoutTable::fromArray(['tiers' => $get('tiers') ?: [], 'extra' => ['trainer' => $get('extra_trainer'), 'facility' => $get('extra_facility')]]);
        $problems = $payout->mismatches($branch->priceTable());

        return $problems ? 'ยังไม่ตรงกับราคาของสาขา: '.implode(' · ', $problems) : '';
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $branch = Branch::findOrFail($state['branch_id']);
        $payout = PayoutTable::fromArray(['tiers' => $state['tiers'], 'extra' => ['trainer' => $state['extra_trainer'], 'facility' => $state['extra_facility']]]);

        if ($problems = $payout->mismatches($branch->priceTable())) {
            Notification::make()->title('บันทึกไม่ได้ ตารางยังไม่ตรงกับราคา')->body(implode(' · ', $problems))->danger()->persistent()->send();

            return;
        }

        $branch->update(['payout' => $payout->toArray()]);
        $changed = Settings::save($state);

        AuditLog::record('payout.settings_changed', $branch, null, ['policy' => $changed, 'table' => $payout->toArray()], null, auth()->user());

        Notification::make()->title('บันทึกแล้ว')->body('ตารางของ '.$branch->name.($changed ? ' และนโยบาย: '.implode(', ', $changed) : ''))->success()->send();
    }
}
