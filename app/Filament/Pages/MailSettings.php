<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Notifications\AccountNotice;
use App\Support\MailSettings as Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Notification as Notify;

/**
 * ตั้งค่าอีเมลแจ้งเตือนจากหลังบ้าน (เฉพาะผู้ดูแลระบบ)
 *
 * SMTP ของกล่องผู้ส่ง (เช่น admin@โดเมน) ชื่อผู้ส่ง และอีเมลผู้ดูแลที่รับสำเนาทุกเหตุการณ์
 * รหัสผ่านไม่ถูกส่งกลับมาแสดง ช่องว่าง = คงค่าเดิม ส่งเมลทดสอบได้ก่อนบันทึก
 */
class MailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'ตั้งค่าระบบ';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'อีเมลแจ้งเตือน';

    protected static ?string $title = 'ตั้งค่าอีเมลแจ้งเตือน';

    protected static ?string $slug = 'mail-settings';

    protected string $view = 'filament.pages.mail-settings';

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
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'host' => $saved['host'] ?? '',
            'port' => $saved['port'] ?? 587,
            'encryption' => $saved['encryption'] ?? 'tls',
            'username' => $saved['username'] ?? '',
            'from_address' => $saved['from_address'] ?? config('mail.from.address'),
            'from_name' => $saved['from_name'] ?? config('mail.from.name'),
            'admin_email' => $saved['admin_email'] ?? config('gym.notifications.admin_email', ''),
            'has_password' => filled($saved['password'] ?? null),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('เซิร์ฟเวอร์ส่งอีเมล (SMTP)')
                    ->description('ใช้กล่องอีเมลของโดเมนตัวเอง เช่น admin@srisawan.com ขอค่า SMTP จากผู้ดูแลอีเมลขององค์กร')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Toggle::make('enabled')
                            ->label('ส่งอีเมลจริงผ่าน SMTP นี้')
                            ->helperText('ปิด = ใช้ค่าจาก .env ตามเดิม (ถ้า .env เป็น log จะไม่ส่งเมลจริง)')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('host')
                            ->label('SMTP host')
                            ->placeholder('smtp.office365.com หรือ mail.srisawan.com')
                            ->required(fn (Get $get) => (bool) $get('enabled'))
                            ->maxLength(200),
                        TextInput::make('port')
                            ->label('พอร์ต')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(587)
                            ->required(fn (Get $get) => (bool) $get('enabled')),
                        Select::make('encryption')
                            ->label('การเข้ารหัส')
                            ->options(Settings::ENCRYPTIONS)
                            ->default('tls')
                            ->selectablePlaceholder(false)
                            ->required(),
                        TextInput::make('username')
                            ->label('ชื่อผู้ใช้ (มักเป็นอีเมลเต็ม)')
                            ->placeholder('admin@srisawan.com')
                            ->autocomplete('off')
                            ->maxLength(200),
                        TextInput::make('password')
                            ->label('รหัสผ่าน SMTP')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder(fn (Get $get) => $get('has_password') ? 'ตั้งไว้แล้ว เว้นว่างเพื่อคงค่าเดิม' : 'รหัสผ่านของกล่องอีเมล หรือ App password')
                            ->helperText('ไม่แสดงค่าที่บันทึกไว้ ใส่ใหม่เฉพาะเมื่อต้องการเปลี่ยน ถ้าเปิดยืนยันตัวตนสองชั้นไว้ต้องใช้ App password')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ]),

                Section::make('ผู้ส่งและสำเนาถึงผู้ดูแล')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('from_address')
                            ->label('อีเมลผู้ส่ง (From)')
                            ->email()
                            ->placeholder('admin@srisawan.com')
                            ->helperText('ควรเป็นกล่องเดียวกับที่ล็อกอิน SMTP ไม่งั้นเซิร์ฟเวอร์ปลายทางอาจตีกลับ')
                            ->required(fn (Get $get) => (bool) $get('enabled'))
                            ->maxLength(200),
                        TextInput::make('from_name')
                            ->label('ชื่อผู้ส่ง')
                            ->placeholder(config('app.name'))
                            ->maxLength(100),
                        TextInput::make('admin_email')
                            ->label('อีเมลผู้ดูแลที่รับสำเนาทุกเหตุการณ์')
                            ->email()
                            ->placeholder('admin@srisawan.com')
                            ->helperText('สมัครใหม่ อนุมัติ จอง รอชำระ ชำระสำเร็จ เลื่อน ยกเลิก คืนเงิน ฯลฯ ส่งสำเนามาที่นี่หนึ่งฉบับต่อเหตุการณ์ เว้นว่าง = ไม่ส่งสำเนา')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('ส่งเมลทดสอบ')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->schema([
                    TextInput::make('to')
                        ->label('ส่งไปที่')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(fn (array $data) => $this->sendTest($data['to'])),
        ];
    }

    /** ใช้ค่าที่พิมพ์อยู่ในฟอร์ม (รหัสผ่านที่บันทึกไว้ถ้าช่องว่าง) ส่งเมลหนึ่งฉบับ ไม่บันทึกอะไร */
    public function sendTest(string $to): void
    {
        $state = $this->form->getState();
        $saved = Settings::load();

        if (($state['enabled'] ?? false) && blank($state['host'] ?? null)) {
            Notification::make()->title('ยังไม่ได้ใส่ SMTP host')->warning()->send();

            return;
        }

        if ($state['enabled'] ?? false) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $state['host'],
                'mail.mailers.smtp.port' => (int) ($state['port'] ?? 587),
                'mail.mailers.smtp.scheme' => ($state['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => filled($state['username'] ?? null) ? $state['username'] : null,
                'mail.mailers.smtp.password' => filled($state['password'] ?? null) ? $state['password'] : ($saved['password'] ?? null),
                'mail.mailers.smtp.url' => null,
            ]);
        }

        if (filled($state['from_address'] ?? null)) {
            config(['mail.from.address' => $state['from_address']]);
        }

        if (filled($state['from_name'] ?? null)) {
            config(['mail.from.name' => $state['from_name']]);
        }

        // ล้าง transport ที่สร้างไว้แล้ว ให้ไปอ่าน config ชุดใหม่
        app('mail.manager')->forgetMailers();

        try {
            Notify::route('mail', $to)->notifyNow(new AccountNotice(
                'ทดสอบการส่งอีเมล',
                'ถ้าได้รับอีเมลฉบับนี้ แปลว่าตั้งค่า SMTP ถูกต้อง ส่งโดย '.(auth()->user()?->displayName() ?? 'ผู้ดูแล').' เมื่อ '.now()->format('d/m/Y H:i').' น.',
                url('/admin/mail-settings'),
            ));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('ส่งไม่สำเร็จ')
                ->body($this->friendlyError($e))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('ส่งแล้ว')
            ->body(config('mail.default') === 'log'
                ? 'ระบบยังตั้งเป็น log เมลถูกบันทึกลง storage/logs แทนการส่งจริง เปิด "ส่งอีเมลจริงผ่าน SMTP นี้" แล้วลองใหม่'
                : 'ส่งไปที่ '.$to.' แล้ว ถ้าไม่เจอให้ดูในกล่องสแปมด้วย')
            ->success()
            ->persistent()
            ->send();
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (($state['enabled'] ?? false) && blank($state['from_address'] ?? null)) {
            Notification::make()->title('บันทึกไม่ได้')->body('ใส่อีเมลผู้ส่งก่อน')->danger()->send();

            return;
        }

        $changed = Settings::save($state);

        // ตารางประวัติผูกกับโมเดลที่มี id ตัวเลข ตั้งค่าระบบไม่มี จึงผูกกับผู้ที่แก้แทน
        AuditLog::record('mail.settings_changed', auth()->user(), null, ['changed' => $changed], null, auth()->user());

        Settings::apply();
        app('mail.manager')->forgetMailers();

        // ล้างรหัสผ่านออกจากฟอร์ม ไม่ให้ค้างอยู่ในหน้าเว็บ
        $this->mount();

        Notification::make()
            ->title('บันทึกแล้ว')
            ->body($changed ? 'เปลี่ยน: '.implode(', ', $changed) : 'ไม่มีอะไรเปลี่ยน')
            ->success()
            ->send();
    }

    public function statusLine(): string
    {
        return Settings::summary();
    }

    protected function friendlyError(\Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, '535') || stripos($m, 'authentication') !== false => 'เซิร์ฟเวอร์ปฏิเสธชื่อผู้ใช้/รหัสผ่าน (535) ตรวจรหัสผ่าน หรือใช้ App password ถ้าเปิดยืนยันสองชั้น',
            str_contains($m, 'getaddrinfo') || stripos($m, 'could not resolve') !== false => 'หา SMTP host ไม่เจอ ตรวจการสะกดชื่อเซิร์ฟเวอร์',
            stripos($m, 'timed out') !== false || stripos($m, 'Connection refused') !== false => 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ตรวจพอร์ตและการเข้ารหัส (587 = STARTTLS, 465 = SSL) และไฟร์วอลล์ขาออกของเซิร์ฟเวอร์',
            str_contains($m, '550') || str_contains($m, '553') => 'เซิร์ฟเวอร์ไม่ยอมให้ส่งจากอีเมลผู้ส่งนี้ (550/553) ใช้อีเมลผู้ส่งเดียวกับบัญชีที่ล็อกอิน',
            default => mb_strimwidth($m, 0, 300, '…'),
        };
    }
}
