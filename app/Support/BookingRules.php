<?php

namespace App\Support;

/**
 * กติกาการจองที่ปรับได้จาก config/gym.php
 *
 * รวมไว้ที่เดียวเพราะทั้ง BookingService และหน้าเว็บต้องตอบคำถามเดียวกัน
 * ถ้าต่างคนต่างอ่าน config เอง วันหนึ่งหน้าเว็บจะบอกว่าหักเครดิตทั้งที่ระบบไม่ได้หัก
 */
class BookingRules
{
    public const APPROVAL_SELF = 'self';

    public const APPROVAL_ALL = 'all';

    public const APPROVAL_NONE = 'none';

    /** ตัดเครดิตจากแพ็กเกจทุกครั้งที่จองหรือไม่ */
    public static function creditsRequired(): bool
    {
        return (bool) config('gym.booking.require_credits', false);
    }

    /**
     * การจองนี้ต้องรอแอดมินอนุมัติหรือไม่
     *
     * @param  bool  $selfBooked  สมาชิกจองให้ตัวเอง (ไม่ได้ผ่านเทรนเนอร์)
     */
    public static function needsApproval(bool $selfBooked): bool
    {
        return match (config('gym.booking.approval', self::APPROVAL_NONE)) {
            self::APPROVAL_ALL => true,
            self::APPROVAL_NONE => false,
            self::APPROVAL_SELF => $selfBooked,
            default => false,
        };
    }

    /** คนทั่วไปที่สมัครเองต้องรอแอดมินอนุมัติก่อนจองได้หรือไม่ */
    public static function memberRegistrationNeedsApproval(): bool
    {
        return (bool) config('gym.registration.member_approval', true);
    }

    /** สมาชิกจองให้ตัวเองล่วงหน้าได้กี่วัน */
    public static function selfAdvanceDays(): int
    {
        return max(1, (int) config('gym.booking.self_advance_days', 7));
    }

    /** เปิดให้คนทั่วไปสมัครสมาชิกเองหรือไม่ */
    public static function publicRegistrationOpen(): bool
    {
        return (bool) config('gym.registration.allow_public_members', true);
    }
}
