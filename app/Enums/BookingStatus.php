<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case Booked = 'booked';
    case Waitlisted = 'waitlisted';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'จองแล้ว',
            self::Waitlisted => 'อยู่คิวสำรอง',
            self::CheckedIn => 'เช็คอินแล้ว',
            self::Completed => 'มาเล่นแล้ว',
            self::Cancelled => 'ยกเลิก',
            self::NoShow => 'ไม่มาตามนัด',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Booked => 'info',
            self::Waitlisted => 'warning',
            self::CheckedIn => 'success',
            self::Completed => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
        };
    }

    /** นับเป็นที่นั่งที่ถูกใช้อยู่จริง */
    public function occupiesSeat(): bool
    {
        return in_array($this, [self::Booked, self::CheckedIn, self::Completed], true);
    }

    /** ยังยกเลิกได้อยู่ */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Booked, self::Waitlisted], true);
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
