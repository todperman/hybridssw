<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    /** ส่งคำขอแล้ว ถือที่นั่งไว้ระหว่างรอแอดมินอนุมัติ */
    case Pending = 'pending';
    case Booked = 'booked';
    case Waitlisted = 'waitlisted';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'รออนุมัติ',
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
            self::Pending => 'warning',
            self::Booked => 'info',
            self::Waitlisted => 'warning',
            self::CheckedIn => 'success',
            self::Completed => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
        };
    }

    /**
     * นับเป็นที่นั่งที่ถูกใช้อยู่จริง
     * รวมที่รออนุมัติด้วย ไม่งั้นสองคนจะขอที่นั่งสุดท้ายใบเดียวกันแล้วอนุมัติได้ทั้งคู่
     */
    public function occupiesSeat(): bool
    {
        return in_array($this, [self::Pending, self::Booked, self::CheckedIn, self::Completed], true);
    }

    /** ยังยกเลิกได้อยู่ */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Booked, self::Waitlisted], true);
    }

    /** สถานะที่กินที่นั่งในรอบที่ยังไม่จบ ใช้ทั้งนับที่นั่งและกันจองซ้ำ */
    public static function seatHolding(): array
    {
        return [self::Pending->value, self::Booked->value, self::CheckedIn->value, self::Completed->value];
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
