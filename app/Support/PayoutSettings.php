<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * นโยบายส่วนแบ่งเทรนเนอร์ (ทั้งระบบ) ตั้งจากหลังบ้าน → ตั้งค่าระบบ → ส่วนแบ่งเทรนเนอร์
 * ตัวเลขต่อจำนวนคนอยู่รายสาขา (branches.payout / PayoutTable) ส่วนนี้คือกติการอบ ๆ
 */
class PayoutSettings
{
    public const KEY = 'payout';

    public const INTERNAL = ['none' => 'ไม่ได้ส่วนแบ่ง (เป็นพนักงาน มีเงินเดือน)', 'table' => 'ได้ตามตารางเดียวกับเทรนเนอร์ภายนอก'];

    public const DISCOUNTS = ['full' => 'ได้เต็มตามตาราง (ยิมรับส่วนลด/สิทธิ์ฟรีเอง)', 'net' => 'ลดตามสัดส่วนที่ลูกค้าจ่ายจริง'];

    public const EARN_WHEN = ['session_end' => 'เมื่อหมดเวลาใช้งานจริง', 'confirmed' => 'ทันทีที่การจองยืนยัน'];

    public const CYCLES = ['monthly' => 'รายเดือน', 'biweekly' => 'ทุก 2 สัปดาห์', 'weekly' => 'รายสัปดาห์'];

    /** @return array{internal: string, discounts: string, earn_when: string, cycle: string, cycle_note: string, visible: bool} */
    public static function load(): array
    {
        $raw = AppSetting::get(self::KEY, []);
        $raw = is_array($raw) ? $raw : [];

        return [
            'internal' => array_key_exists($raw['internal'] ?? '', self::INTERNAL) ? $raw['internal'] : 'none',
            'discounts' => array_key_exists($raw['discounts'] ?? '', self::DISCOUNTS) ? $raw['discounts'] : 'full',
            'earn_when' => array_key_exists($raw['earn_when'] ?? '', self::EARN_WHEN) ? $raw['earn_when'] : 'session_end',
            'cycle' => array_key_exists($raw['cycle'] ?? '', self::CYCLES) ? $raw['cycle'] : 'monthly',
            'cycle_note' => (string) ($raw['cycle_note'] ?? 'โอนภายในวันที่ 5 ของเดือนถัดไป'),
            'visible' => array_key_exists('visible', $raw) ? (bool) $raw['visible'] : true,
        ];
    }

    /** @return array<int, string> ช่องที่เปลี่ยน */
    public static function save(array $input): array
    {
        $current = self::load();
        $next = [
            'internal' => array_key_exists($input['internal'] ?? '', self::INTERNAL) ? $input['internal'] : 'none',
            'discounts' => array_key_exists($input['discounts'] ?? '', self::DISCOUNTS) ? $input['discounts'] : 'full',
            'earn_when' => array_key_exists($input['earn_when'] ?? '', self::EARN_WHEN) ? $input['earn_when'] : 'session_end',
            'cycle' => array_key_exists($input['cycle'] ?? '', self::CYCLES) ? $input['cycle'] : 'monthly',
            'cycle_note' => trim((string) ($input['cycle_note'] ?? '')),
            'visible' => (bool) ($input['visible'] ?? true),
        ];

        AppSetting::put(self::KEY, $next);

        return array_keys(array_filter($next, fn ($v, $k) => ($current[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
    }

    /** ข้อความนโยบายสั้น ๆ ไว้แสดงให้เทรนเนอร์ */
    public static function policyLines(): array
    {
        $s = self::load();

        return array_values(array_filter([
            'นับเป็นรายได้'.self::EARN_WHEN[$s['earn_when']],
            $s['discounts'] === 'full' ? 'ส่วนลดหรือสิทธิ์ฟรีของลูกค้าไม่ลดส่วนแบ่งของคุณ' : 'ส่วนแบ่งลดตามสัดส่วนที่ลูกค้าจ่ายจริง',
            'จ่าย'.self::CYCLES[$s['cycle']].($s['cycle_note'] ? ' · '.$s['cycle_note'] : ''),
            'การจองที่ถูกยกเลิกหรือหมดอายุไม่นับ',
        ]));
    }
}
