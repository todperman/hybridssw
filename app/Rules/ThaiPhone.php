<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * เบอร์โทรไทย 10 หลัก ขึ้นต้นด้วย 0
 *
 * ยอมให้พิมพ์เว้นวรรค ขีด หรือวงเล็บมาได้ เพราะคนกรอกมักคัดลอกมาจากที่อื่น
 * แล้วค่อยตัดทิ้งตอนตรวจ ถ้าบังคับให้พิมพ์ติดกันอย่างเดียวจะเจอ error บ่อยโดยไม่จำเป็น
 */
class ThaiPhone implements ValidationRule
{
    /** เบอร์บ้าน/สำนักงานของไทยมี 9 หลัก (เช่น 02xxxxxxx) จึงต้องยอมให้สั้นกว่าได้ในบางที่ */
    public function __construct(protected bool $allowLandline = false) {}

    /** ใช้กับเบอร์ขององค์กร เช่น เบอร์สาขา ที่อาจเป็นเบอร์บ้าน 9 หลัก */
    public static function anyLine(): self
    {
        return new self(allowLandline: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = self::digits($value);

        // ปล่อยค่าว่างผ่าน ความ "ต้องกรอก" เป็นหน้าที่ของกฎ required
        // ถ้ากฎนี้ไปบังคับเอง ช่องเบอร์ที่ไม่บังคับในหลังบ้านจะบันทึกไม่ได้เลย
        if ($digits === '') {
            return;
        }

        $lengths = $this->allowLandline ? [9, 10] : [10];

        if (! in_array(strlen($digits), $lengths, true)) {
            $fail($this->allowLandline
                ? 'เบอร์โทรต้องมี 9 หลัก (เบอร์บ้าน) หรือ 10 หลัก (มือถือ)'
                : 'เบอร์โทรต้องมี 10 หลัก');

            return;
        }

        if (! str_starts_with($digits, '0')) {
            $fail('เบอร์โทรต้องขึ้นต้นด้วย 0');
        }
    }

    /** เหลือเฉพาะตัวเลข ใช้ทั้งตอนตรวจและตอนบันทึกให้รูปแบบในฐานข้อมูลตรงกันทุกเบอร์ */
    public static function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
