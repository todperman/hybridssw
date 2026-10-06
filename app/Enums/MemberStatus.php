<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MemberStatus: string implements HasColor, HasLabel
{
    /** สมัครเองแล้ว รอแอดมินอนุมัติก่อนจองได้ */
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'รออนุมัติ',
            self::Rejected => 'ไม่ผ่านการอนุมัติ',
            self::Active => 'ปกติ',
            self::Suspended => 'ถูกระงับสิทธิ์',
            self::Inactive => 'ไม่ใช้งาน',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Rejected => 'danger',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Inactive => 'gray',
        };
    }

    /** ยังไม่ผ่านการอนุมัติการสมัคร ใช้หน้าจองไม่ได้ เห็นได้แค่หน้าสถานะการสมัคร */
    public function awaitsApproval(): bool
    {
        return in_array($this, [self::Pending, self::Rejected], true);
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
