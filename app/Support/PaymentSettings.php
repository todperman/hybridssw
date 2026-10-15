<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * ตั้งค่าช่องทางชำระเงินจากหลังบ้าน แทนการแก้ .env บนเซิร์ฟเวอร์
 *
 * เก็บใน app_settings คีย์ 'payments' คีย์ลับ (secret key, webhook secret) เข้ารหัสด้วย APP_KEY ก่อนลงฐานข้อมูล
 * ค่าที่ตั้งไว้ทับค่าใน .env ตอนบูตแอป ช่องไหนว่างใช้ค่าจาก .env ต่อ จึงย้ายมาทีละส่วนได้
 *
 * โครงสร้าง:
 *   driver  manual | omise
 *   mode    test | live  (ชุดคีย์ที่ใช้งาน)
 *   methods ช่องทางที่ให้ลูกค้าเลือก
 *   test / live  { public_key, secret_key, webhook_secret }
 */
final class PaymentSettings
{
    public const KEY = 'payments';

    public const SECRET_FIELDS = ['secret_key', 'webhook_secret'];

    /** @return array<string, mixed> ค่าที่เก็บไว้ ถอดรหัสคีย์ลับแล้ว ไม่มี = [] */
    public static function load(): array
    {
        $raw = AppSetting::get(self::KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        foreach (['test', 'live'] as $mode) {
            foreach (self::SECRET_FIELDS as $field) {
                if (filled($raw[$mode][$field] ?? null)) {
                    $raw[$mode][$field] = self::decrypt($raw[$mode][$field]);
                }
            }
        }

        return $raw;
    }

    /**
     * บันทึก คีย์ลับที่ส่งมาว่างแปลว่า "คงค่าเดิม" ฟอร์มจะได้ไม่ต้องโชว์คีย์ลับกลับมาให้เห็น
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string> รายการช่องที่เปลี่ยน (ไม่มีค่า) ไว้ลงประวัติ
     */
    public static function save(array $input): array
    {
        $current = self::load();
        $next = [
            'driver' => in_array($input['driver'] ?? null, ['manual', 'omise'], true) ? $input['driver'] : 'manual',
            'mode' => ($input['mode'] ?? 'test') === 'live' ? 'live' : 'test',
            'methods' => array_values(array_intersect((array) ($input['methods'] ?? []), array_keys(\App\Services\Payments\Omise\PaymentMethods::all()))),
        ];

        foreach (['test', 'live'] as $mode) {
            $next[$mode] = [
                'public_key' => trim((string) ($input[$mode]['public_key'] ?? '')),
                'secret_key' => filled($input[$mode]['secret_key'] ?? null) ? trim($input[$mode]['secret_key']) : ($current[$mode]['secret_key'] ?? ''),
                'webhook_secret' => filled($input[$mode]['webhook_secret'] ?? null) ? trim($input[$mode]['webhook_secret']) : ($current[$mode]['webhook_secret'] ?? ''),
            ];
        }

        $changed = [];

        foreach (['driver', 'mode', 'methods'] as $field) {
            if (($current[$field] ?? null) !== $next[$field]) {
                $changed[] = $field;
            }
        }

        foreach (['test', 'live'] as $mode) {
            foreach (['public_key', 'secret_key', 'webhook_secret'] as $field) {
                if (($current[$mode][$field] ?? '') !== $next[$mode][$field]) {
                    $changed[] = $mode.'.'.$field;
                }
            }
        }

        $stored = $next;

        foreach (['test', 'live'] as $mode) {
            foreach (self::SECRET_FIELDS as $field) {
                $stored[$mode][$field] = filled($next[$mode][$field]) ? Crypt::encryptString($next[$mode][$field]) : '';
            }
        }

        AppSetting::put(self::KEY, $stored);

        return $changed;
    }

    /** ทับ config ด้วยค่าที่ตั้งไว้ในหลังบ้าน เรียกตอนบูตแอป */
    public static function apply(): void
    {
        $settings = self::load();

        if ($settings === []) {
            return;
        }

        if (filled($settings['driver'] ?? null)) {
            config(['gym.payments.driver' => $settings['driver']]);
        }

        if (! empty($settings['methods'])) {
            config(['services.omise.methods' => $settings['methods']]);
        }

        $keys = $settings[$settings['mode'] ?? 'test'] ?? [];

        foreach (['public_key', 'secret_key', 'webhook_secret'] as $field) {
            if (filled($keys[$field] ?? null)) {
                config(['services.omise.'.$field => $keys[$field]]);
            }
        }
    }

    /** ตรวจรูปแบบคีย์ว่าตรงโหมด ไม่ให้เผลอใส่คีย์จริงในช่องทดสอบหรือกลับกัน */
    public static function keyProblem(string $mode, ?string $publicKey, ?string $secretKey): ?string
    {
        foreach ([['pkey_', $publicKey, 'Public key'], ['skey_', $secretKey, 'Secret key']] as [$prefix, $value, $label]) {
            if (blank($value)) {
                continue;
            }

            if (! str_starts_with($value, $prefix)) {
                return "{$label} ต้องขึ้นต้นด้วย {$prefix}";
            }

            $isTest = str_contains($value, '_test_');

            if ($mode === 'test' && ! $isTest) {
                return "{$label} ในช่องทดสอบเป็นคีย์โหมดจริง";
            }

            if ($mode === 'live' && $isTest) {
                return "{$label} ในช่องใช้งานจริงเป็นคีย์ทดสอบ";
            }
        }

        return null;
    }

    protected static function decrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // APP_KEY เปลี่ยน ค่าเดิมอ่านไม่ได้ ให้ถือว่าว่างแล้วไปใช้ .env แทน
            return '';
        }
    }
}
