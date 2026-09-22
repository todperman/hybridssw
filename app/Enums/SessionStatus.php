<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SessionStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'เปิดจอง',
            self::Closed => 'ปิดรับจอง',
            self::Cancelled => 'ยกเลิกรอบ',
            self::Completed => 'จบรอบแล้ว',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'warning',
            self::Cancelled => 'danger',
            self::Completed => 'gray',
        };
    }

    public function acceptsBookings(): bool
    {
        return $this === self::Open;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
