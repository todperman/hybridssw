<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';
    case Trainer = 'trainer';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'ผู้ดูแลระบบ',
            self::Manager => 'ผู้จัดการสาขา',
            self::Staff => 'เจ้าหน้าที่หน้าเคาน์เตอร์',
            self::Trainer => 'เทรนเนอร์',
            self::Member => 'ลูกทีม',
        };
    }

    /** เข้าหลังบ้าน Filament ได้หรือไม่ */
    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Staff], true);
    }

    /** เห็นข้อมูลได้ทุกสาขาหรือไม่ */
    public function isGlobal(): bool
    {
        return $this === self::Admin;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
