<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** เพศ ใช้ชุดเดียวกันทั้งสมาชิกและเทรนเนอร์ ทุกหน้า (สมัคร ทีม หลังบ้าน) */
enum Gender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'ชาย',
            self::Female => 'หญิง',
            self::Other => 'อื่น ๆ',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $g) => [$g->value => $g->label()])->all();
    }

    public static function labelFor(?string $value): ?string
    {
        return $value === null ? null : (self::tryFrom($value)?->label() ?? $value);
    }
}
