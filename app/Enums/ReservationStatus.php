<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasLabel
{
    /** กันเวลาไว้ รอผู้ชำระเงินจ่ายให้เสร็จภายในเวลาที่กำหนด */
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'รอชำระเงิน',
            self::Confirmed => 'ยืนยันการจอง',
            self::Expired => 'หมดอายุ',
            self::Cancelled => 'ยกเลิก',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Confirmed => 'success',
            self::Expired => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /** สถานะที่ยังถือเวลาของยิม Trainer และผู้เข้าร่วมอยู่ */
    public function holdsSlots(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed], true);
    }

    public static function holding(): array
    {
        return [self::PendingPayment->value, self::Confirmed->value];
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
