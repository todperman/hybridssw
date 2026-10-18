<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * ตั้งค่าเซิร์ฟเวอร์ส่งอีเมล (SMTP) ผู้ส่ง และอีเมลผู้ดูแลที่รับสำเนา จากหลังบ้าน
 * เก็บใน app_settings คีย์ mail รหัสผ่านเข้ารหัสด้วย APP_KEY
 * เมื่อเปิดใช้ ค่าที่นี่ทับ MAIL_* ใน .env ตอนบูต (ดู AppServiceProvider)
 */
class MailSettings
{
    public const KEY = 'mail';

    public const ENCRYPTIONS = [
        'tls' => 'STARTTLS (พอร์ต 587 ที่พบบ่อย)',
        'ssl' => 'SSL/TLS (พอร์ต 465)',
        'none' => 'ไม่เข้ารหัส (เฉพาะเซิร์ฟเวอร์ภายใน)',
    ];

    /** @return array<string, mixed> */
    public static function load(): array
    {
        $raw = AppSetting::get(self::KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        if (filled($raw['password'] ?? null)) {
            $raw['password'] = self::decrypt($raw['password']);
        }

        return $raw;
    }

    /**
     * รหัสผ่านที่ส่งมาว่างแปลว่า "คงค่าเดิม" ฟอร์มจะได้ไม่ต้องโชว์รหัสกลับมา
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string> ช่องที่เปลี่ยน (ไม่มีค่า) ไว้ลงประวัติ
     */
    public static function save(array $input): array
    {
        $current = self::load();

        $next = [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'host' => trim((string) ($input['host'] ?? '')),
            'port' => (int) ($input['port'] ?? 587) ?: 587,
            'encryption' => array_key_exists($input['encryption'] ?? '', self::ENCRYPTIONS) ? $input['encryption'] : 'tls',
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => filled($input['password'] ?? null) ? (string) $input['password'] : ($current['password'] ?? ''),
            'from_address' => strtolower(trim((string) ($input['from_address'] ?? ''))),
            'from_name' => trim((string) ($input['from_name'] ?? '')),
            'admin_email' => strtolower(trim((string) ($input['admin_email'] ?? ''))),
        ];

        $changed = [];

        foreach ($next as $field => $value) {
            if (($current[$field] ?? null) !== $value) {
                $changed[] = $field;
            }
        }

        $stored = $next;
        $stored['password'] = filled($next['password']) ? Crypt::encryptString($next['password']) : '';

        AppSetting::put(self::KEY, $stored);

        return $changed;
    }

    /** ทับ config ด้วยค่าที่ตั้งไว้ในหลังบ้าน เรียกตอนบูตแอป */
    public static function apply(): void
    {
        $s = self::load();

        if ($s === []) {
            return;
        }

        // อีเมลผู้ดูแลที่รับสำเนาใช้ได้แม้ยังส่งผ่าน .env อยู่
        if (filled($s['admin_email'] ?? null)) {
            config(['gym.notifications.admin_email' => $s['admin_email']]);
        }

        if (empty($s['enabled']) || blank($s['host'] ?? null)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $s['host'],
            'mail.mailers.smtp.port' => (int) ($s['port'] ?? 587),
            'mail.mailers.smtp.scheme' => ($s['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => filled($s['username'] ?? null) ? $s['username'] : null,
            'mail.mailers.smtp.password' => filled($s['password'] ?? null) ? $s['password'] : null,
            'mail.mailers.smtp.url' => null,
            'gym.notifications.mail' => true,
        ]);

        if (filled($s['from_address'] ?? null)) {
            config(['mail.from.address' => $s['from_address']]);
        }

        if (filled($s['from_name'] ?? null)) {
            config(['mail.from.name' => $s['from_name']]);
        }
    }

    /** สรุปว่าตอนนี้ระบบส่งเมลผ่านอะไร ไว้โชว์ในหน้าตั้งค่าและ gym:doctor */
    public static function summary(): string
    {
        $mailer = config('mail.default');

        if ($mailer === 'log') {
            return 'ยังไม่ส่งอีเมลจริง (บันทึกลง log อย่างเดียว)';
        }

        if ($mailer === 'smtp') {
            return 'ส่งผ่าน SMTP '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')
                .' จาก '.config('mail.from.address');
        }

        return 'ส่งผ่าน '.$mailer.' จาก '.config('mail.from.address');
    }

    protected static function decrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // APP_KEY เปลี่ยน ค่าเดิมอ่านไม่ได้ ให้ถือว่าว่าง
            return '';
        }
    }
}
