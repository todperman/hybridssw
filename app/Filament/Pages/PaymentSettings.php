<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\Payments\Omise\OmiseClient;
use App\Services\Payments\Omise\OmiseException;
use App\Services\Payments\Omise\PaymentMethods;
use App\Support\PaymentSettings as Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

/**
 * ตั้งค่าช่องทางชำระเงินและคีย์ Omise จากหลังบ้าน (เฉพาะผู้ดูแลระบบ)
 *
 * คีย์ลับไม่ถูกส่งกลับมาแสดงในฟอร์ม ช่องว่าง = คงค่าเดิม
 * ทดสอบการเชื่อมต่อได้ก่อนบันทึก และทุกการเปลี่ยนลงประวัติ (ไม่เก็บค่าคีย์)
 */
class PaymentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'ตั้งค่าระบบ';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'ชำระเงิน / Omise';

    protected static ?string $title = 'ตั้งค่าชำระเงิน';

    protected static ?string $slug = 'payment-settings';

    protected string $view = 'filament.pages.payment-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $saved = Settings::load();

        $this->form->fill([
            'driver' => $saved['driver'] ?? config('gym.payments.driver', 'manual'),
            'mode' => $saved['mode'] ?? 'test',
            'methods' => $saved['methods'] ?? config('services.omise.methods', []),
            'test' => ['public_key' => $saved['test']['public_key'] ?? ''],
            'live' => ['public_key' => $saved['live']['public_key'] ?? ''],
            // บอกฟอร์มว่าคีย์ลับตั้งไว้แล้วหรือยัง โดยไม่ส่งค่าจริงลงหน้าเว็บ
            'has' => [
                'test_secret' => filled($saved['test']['secret_key'] ?? null),
                'test_webhook' => filled($saved['test']['webhook_secret'] ?? null),
                'live_secret' => filled($saved['live']['secret_key'] ?? null),
                'live_webhook' => filled($saved['live']['webhook_secret'] ?? null),
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ช่องทางรับชำระ')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Radio::make('driver')
                            ->label('รับชำระผ่าน')
                            ->options([
                                'manual' => 'โอนเงิน แล้วแอดมินกดบันทึกรับชำระ',
                                'omise' => 'Omise (ลูกค้าจ่ายออนไลน์ ยืนยันอัตโนมัติ)',
                            ])
                            ->required()
                            ->live(),
                        Radio::make('mode')
                            ->label('ชุดคีย์ Omise ที่ใช้งาน')
                            ->options([
                                'test' => 'โหมดทดสอบ (ไม่ตัดเงินจริง)',
                                'live' => 'โหมดใช้งานจริง',
                            ])
                            ->required()
                            ->visible(fn (Get $get) => $get('driver') === 'omise'),
                        CheckboxList::make('methods')
                            ->label('ช่องทางที่ให้ลูกค้าเลือก')
                            ->options(collect(PaymentMethods::all())->map(fn ($m) => $m['label'])->all())
                            ->descriptions(collect(PaymentMethods::all())->map(fn ($m) => $m['hint'])->all())
                            ->columns(['default' => 1, 'sm' => 2])
                            ->helperText('แต่ละช่องทางต้องขอ Omise เปิดในบัญชีด้วย ระบบแสดงให้ลูกค้าเฉพาะที่เปิดแล้วจริง')
                            ->visible(fn (Get $get) => $get('driver') === 'omise')
                            ->columnSpanFull(),
                    ]),

                Tabs::make('keys')
                    ->tabs([
                        $this->keyTab('test', 'คีย์โหมดทดสอบ', 'pkey_test_… / skey_test_…'),
                        $this->keyTab('live', 'คีย์โหมดใช้งานจริง', 'pkey_live_… / skey_live_…'),
                    ])
                    ->visible(fn (Get $get) => $get('driver') === 'omise')
                    ->columnSpanFull(),
            ]);
    }

    protected function keyTab(string $mode, string $title, string $example): Tab
    {
        $hasSecret = "has.{$mode}_secret";
        $hasWebhook = "has.{$mode}_webhook";

        return Tab::make($title)
            ->schema([
                TextInput::make("{$mode}.public_key")
                    ->label('Public key')
                    ->placeholder($example)
                    ->maxLength(100),
                TextInput::make("{$mode}.secret_key")
                    ->label('Secret key')
                    ->password()
                    ->revealable()
                    ->placeholder(fn (Get $get) => $get($hasSecret) ? 'ตั้งไว้แล้ว เว้นว่างเพื่อคงค่าเดิม' : 'skey_…')
                    ->helperText('ไม่แสดงค่าที่บันทึกไว้ ใส่ใหม่เฉพาะเมื่อต้องการเปลี่ยน')
                    ->maxLength(100),
                TextInput::make("{$mode}.webhook_secret")
                    ->label('Webhook secret')
                    ->password()
                    ->revealable()
                    ->placeholder(fn (Get $get) => $get($hasWebhook) ? 'ตั้งไว้แล้ว เว้นว่างเพื่อคงค่าเดิม' : 'จาก Omise Dashboard → Webhooks')
                    ->helperText('ตั้ง Webhook URL ใน Omise เป็น '.route('webhooks.omise').' แล้วคัดลอก secret ของโหมดนี้มาใส่')
                    ->maxLength(200),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('ทดสอบการเชื่อมต่อ')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(fn () => $this->testConnection()),
        ];
    }

    /** ใช้คีย์ที่พิมพ์อยู่ในฟอร์ม (หรือที่บันทึกไว้ถ้าช่องว่าง) ถาม Omise ว่าคีย์ใช้ได้และเปิดช่องทางอะไร */
    public function testConnection(): void
    {
        $state = $this->form->getState();
        $mode = $state['mode'] ?? 'test';
        $saved = Settings::load();
        $public = filled($state[$mode]['public_key'] ?? null) ? $state[$mode]['public_key'] : ($saved[$mode]['public_key'] ?? '');
        $secret = filled($state[$mode]['secret_key'] ?? null) ? $state[$mode]['secret_key'] : ($saved[$mode]['secret_key'] ?? '');

        if ($problem = Settings::keyProblem($mode, $public, $secret)) {
            Notification::make()->title('คีย์ไม่ถูกต้อง')->body($problem)->danger()->send();

            return;
        }

        if (blank($public) || blank($secret)) {
            Notification::make()->title('ยังไม่ได้ใส่คีย์ของโหมดนี้ครบ')->warning()->send();

            return;
        }

        config(['services.omise.public_key' => $public, 'services.omise.secret_key' => $secret]);

        try {
            $client = app(OmiseClient::class);
            $account = $client->account();
            $enabled = collect($client->capability()['payment_methods'] ?? [])->pluck('name')->all();
        } catch (OmiseException $e) {
            Notification::make()->title('เชื่อมต่อไม่ได้')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $available = PaymentMethods::available($state['methods'] ?? [], $enabled);
        $missing = array_diff($state['methods'] ?? [], array_keys($available));

        Notification::make()
            ->title('เชื่อมต่อ Omise ได้ · '.($account['email'] ?? '').' · '.($mode === 'live' ? 'โหมดจริง' : 'โหมดทดสอบ'))
            ->body('ช่องทางที่ใช้ได้: '.(implode(', ', array_keys($available)) ?: 'ไม่มี')
                .($missing ? ' · ยังไม่เปิดในบัญชี: '.implode(', ', $missing) : ''))
            ->{$missing ? 'warning' : 'success'}()
            ->persistent()
            ->send();
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (($state['driver'] ?? '') === 'omise') {
            $saved = Settings::load();
            $mode = $state['mode'] ?? 'test';

            foreach (['test', 'live'] as $m) {
                $secret = filled($state[$m]['secret_key'] ?? null) ? $state[$m]['secret_key'] : ($saved[$m]['secret_key'] ?? null);

                if ($problem = Settings::keyProblem($m, $state[$m]['public_key'] ?? null, $secret)) {
                    Notification::make()->title('บันทึกไม่ได้')->body($problem)->danger()->send();

                    return;
                }
            }

            $activePublic = $state[$mode]['public_key'] ?? '';
            $activeSecret = filled($state[$mode]['secret_key'] ?? null) ? $state[$mode]['secret_key'] : ($saved[$mode]['secret_key'] ?? '');

            if (blank($activePublic) || blank($activeSecret)) {
                Notification::make()->title('บันทึกไม่ได้')->body('ใส่ Public key และ Secret key ของโหมดที่เลือกใช้งานก่อน')->danger()->send();

                return;
            }
        }

        $changed = Settings::save($state);

        // ช่องทางที่บัญชีเปิด อาจเปลี่ยนตามคีย์ ให้ถามใหม่
        Cache::flush();

        // ตารางประวัติผูกกับโมเดลที่มี id ตัวเลข ตั้งค่าระบบไม่มี จึงผูกกับผู้ที่แก้แทน
        AuditLog::record('payments.settings_changed', auth()->user(), null, ['changed' => $changed], null, auth()->user());

        // ล้างคีย์ลับออกจากฟอร์ม ไม่ให้ค้างอยู่ในหน้าเว็บ
        $this->mount();

        Notification::make()
            ->title('บันทึกแล้ว')
            ->body($changed ? 'เปลี่ยน: '.implode(', ', $changed) : 'ไม่มีอะไรเปลี่ยน')
            ->success()
            ->send();
    }
}
