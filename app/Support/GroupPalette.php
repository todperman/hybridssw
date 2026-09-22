<?php

namespace App\Support;

/**
 * สีประจำกลุ่มลูกทีม ให้เทรนเนอร์เลือกเองเพื่อแยกกลุ่มด้วยสายตา
 *
 * ทุกสีคุมความสว่างและความอิ่มสีให้เท่ากัน ต่างกันแค่สีสัน
 * จึงดูเป็นชุดเดียวกันและไม่มีสีไหนเด่นข่มสีอื่น
 *
 * base = สีเข้มสำหรับแถบและจุดเน้น
 * soft = พื้นหลังอ่อนสำหรับป้ายกำกับ
 * ink  = สีตัวอักษรที่อ่านออกบนพื้น soft
 */
class GroupPalette
{
    public const COLORS = [
        'ocean' => ['label' => 'ฟ้าทะเล', 'base' => '#008ab0', 'soft' => '#caf1ff', 'ink' => '#005a78'],
        'teal' => ['label' => 'เทอร์คอยซ์', 'base' => '#00918e', 'soft' => '#c8f4f1', 'ink' => '#00605e'],
        'leaf' => ['label' => 'เขียวใบไม้', 'base' => '#30905d', 'soft' => '#d2f3dd', 'ink' => '#0a5f38'],
        'lime' => ['label' => 'เขียวมะนาว', 'base' => '#70862a', 'soft' => '#e4efce', 'ink' => '#475808'],
        'gold' => ['label' => 'ทอง', 'base' => '#9b7500', 'soft' => '#f7e8c8', 'ink' => '#684a00'],
        'amber' => ['label' => 'ส้มอำพัน', 'base' => '#b26530', 'soft' => '#ffe2cf', 'ink' => '#793d11'],
        'coral' => ['label' => 'ปะการัง', 'base' => '#b85b5e', 'soft' => '#ffdedd', 'ink' => '#7e3638'],
        'violet' => ['label' => 'ม่วง', 'base' => '#8e67b4', 'soft' => '#f1e2ff', 'ink' => '#5d3f7b'],
    ];

    public const DEFAULT = 'ocean';

    /** คืนค่าสีเสมอ ถึงจะเก็บคีย์เก่าที่ไม่มีแล้วไว้ในฐานข้อมูล */
    public static function get(?string $key): array
    {
        return self::COLORS[$key] ?? self::COLORS[self::DEFAULT];
    }

    public static function keys(): array
    {
        return array_keys(self::COLORS);
    }

    /** ใช้กับกฎ validation */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::keys());
    }
}
