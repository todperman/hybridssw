<?php

namespace App\Support;

/**
 * กติกาการสมัครที่ปรับได้จาก config/gym.php
 * รวมไว้ที่เดียว หน้าเว็บกับตัวตรวจระบบจะได้ตอบคำถามเดียวกัน
 */
class RegistrationRules
{
    /** เปิดให้คนทั่วไปสมัครสมาชิกเองหรือไม่ */
    public static function publicRegistrationOpen(): bool
    {
        return (bool) config('gym.registration.allow_public_members', true);
    }

    /** คนทั่วไปที่สมัครเองต้องรอแอดมินอนุมัติก่อนจองได้หรือไม่ */
    public static function memberRegistrationNeedsApproval(): bool
    {
        return (bool) config('gym.registration.member_approval', true);
    }
}
