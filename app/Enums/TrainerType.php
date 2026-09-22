<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TrainerType: string implements HasLabel
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'เทรนเนอร์ภายใน',
            self::External => 'เทรนเนอร์ภายนอก',
        };
    }

    /** เทรนเนอร์ภายในอนุมัติอัตโนมัติ ภายนอกต้องให้แอดมินตรวจเอกสารก่อน */
    public function isAutoApproved(): bool
    {
        return $this === self::Internal;
    }

    /** ค่าเริ่มต้นของประเภทนี้จาก config/gym.php */
    protected function defaults(): array
    {
        return config("gym.trainer_defaults.{$this->value}", []);
    }

    /** จำนวนที่นั่งสูงสุดที่จองได้ต่อรอบ เมื่อไม่ได้ตั้งค่าเฉพาะราย */
    public function defaultMaxSeatsPerSession(): int
    {
        return (int) ($this->defaults()['seats_per_session'] ?? 5);
    }

    /** จองล่วงหน้าได้กี่วัน เมื่อไม่ได้ตั้งค่าเฉพาะราย */
    public function defaultAdvanceBookingDays(): int
    {
        return (int) ($this->defaults()['advance_booking_days'] ?? 7);
    }

    /** ขนาดทีมสูงสุด null แปลว่าไม่จำกัด */
    public function defaultMaxTeamSize(): ?int
    {
        $value = $this->defaults()['max_team_size'] ?? null;

        return ($value === null || $value === '') ? null : (int) $value;
    }

    /** เทรนเนอร์ภายนอกต้องมีใบรับรองที่ยังไม่หมดอายุ */
    public function requiresCertification(): bool
    {
        return $this === self::External;
    }

    /**
     * ประเภทที่เปิดให้สมัครเองที่หน้าบ้าน
     *
     * เทรนเนอร์ภายนอกมาก่อนเพราะเป็นกลุ่มที่สมัครเองเป็นหลัก
     * ส่วนเทรนเนอร์ภายในเปิด/ปิดได้จาก config เพราะบางช่วงให้แอดมินสร้างให้เท่านั้น
     * หลังบ้านยังใช้ cases() ตามปกติ ไม่ถูกกรองด้วยค่านี้
     *
     * @return array<int, self>
     */
    public static function orderedForRegistration(): array
    {
        $types = [self::External];

        if (config('gym.registration.allow_internal', false)) {
            $types[] = self::Internal;
        }

        return $types;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
