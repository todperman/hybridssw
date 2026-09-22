<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SessionMode: string implements HasLabel
{
    /** เทรนเนอร์หลายคนแบ่งที่นั่งในรอบเดียวกันได้ */
    case Shared = 'shared';

    /** เทรนเนอร์คนแรกที่จองเหมาทั้งรอบ คนอื่นจองไม่ได้ */
    case Exclusive = 'exclusive';

    public function label(): string
    {
        return match ($this) {
            self::Shared => 'แบ่งที่นั่งร่วมกัน',
            self::Exclusive => 'เหมารอบ (เทรนเนอร์เดียว)',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
