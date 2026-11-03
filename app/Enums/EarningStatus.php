<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EarningStatus: string implements HasColor, HasLabel
{
    /** การจองยืนยันแล้ว แต่ยังไม่ถึงเวลาใช้งาน */
    case Accrued = 'accrued';
    /** ใช้งานจบแล้ว รอจ่าย */
    case Payable = 'payable';
    case Paid = 'paid';
    /** การจองถูกยกเลิก/คืนเงิน ไม่มีส่วนแบ่ง */
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Accrued => 'รอถึงเวลาใช้งาน',
            self::Payable => 'รอจ่าย',
            self::Paid => 'จ่ายแล้ว',
            self::Void => 'ยกเลิก',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accrued => 'info',
            self::Payable => 'warning',
            self::Paid => 'success',
            self::Void => 'gray',
        };
    }

    /** สถานะที่นับเป็นรายได้ (ยังไม่ถูกยกเลิก) */
    public static function counting(): array
    {
        return [self::Accrued->value, self::Payable->value, self::Paid->value];
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
