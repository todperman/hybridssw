<?php

namespace App\Exceptions;

use Exception;

/**
 * ข้อผิดพลาดเชิงกติกาของการจอง ข้อความพร้อมแสดงให้ผู้ใช้เห็นได้เลย
 */
class BookingException extends Exception
{
    public function __construct(string $message, public readonly string $reason = 'invalid')
    {
        parent::__construct($message);
    }

    public static function sessionNotOpen(): self
    {
        return new self('รอบนี้ปิดรับจองแล้ว', 'session_not_open');
    }

    public static function sessionStarted(): self
    {
        return new self('รอบนี้เริ่มไปแล้ว จองย้อนหลังไม่ได้', 'session_started');
    }

    public static function sessionFull(): self
    {
        return new self('รอบนี้เต็มแล้ว', 'session_full');
    }

    public static function sessionExclusive(): self
    {
        return new self('รอบนี้ถูกเทรนเนอร์ท่านอื่นเหมาไปแล้ว', 'session_exclusive');
    }

    public static function trainerNotApproved(): self
    {
        return new self('บัญชีเทรนเนอร์ยังไม่ได้รับอนุมัติ จองไม่ได้', 'trainer_not_approved');
    }

    public static function trainerContractInactive(): self
    {
        return new self('สัญญาของเทรนเนอร์ยังไม่เริ่มหรือหมดอายุแล้ว', 'trainer_contract_inactive');
    }

    public static function trainerCertificationExpired(): self
    {
        return new self('ใบรับรองของเทรนเนอร์หมดอายุแล้ว กรุณาอัปเดตเอกสาร', 'trainer_certification_expired');
    }

    public static function trainerSeatQuotaReached(int $quota): self
    {
        return new self("เทรนเนอร์จองได้สูงสุด {$quota} ที่นั่งต่อรอบ", 'trainer_seat_quota');
    }

    public static function beyondAdvanceWindow(int $days): self
    {
        return new self("จองล่วงหน้าได้ไม่เกิน {$days} วัน", 'beyond_advance_window');
    }

    public static function memberNotInTeam(): self
    {
        return new self('ลูกทีมคนนี้ไม่ได้อยู่ในทีมของคุณ', 'member_not_in_team');
    }

    public static function memberSuspended(?string $until = null): self
    {
        return new self(
            'ลูกทีมคนนี้ถูกระงับสิทธิ์จอง'.($until ? " ถึง {$until}" : ''),
            'member_suspended'
        );
    }

    public static function memberInactive(): self
    {
        return new self('บัญชีลูกทีมยังไม่พร้อมใช้งาน', 'member_inactive');
    }

    public static function parqNotSigned(): self
    {
        return new self('ลูกทีมยังไม่ได้เซ็นแบบคัดกรองสุขภาพ (PAR-Q)', 'parq_not_signed');
    }

    public static function alreadyBooked(): self
    {
        return new self('ลูกทีมคนนี้จองรอบนี้ไว้แล้ว', 'already_booked');
    }

    public static function timeConflict(string $time): self
    {
        return new self("ลูกทีมมีคิวชนกันอยู่แล้วในช่วง {$time}", 'time_conflict');
    }

    public static function noCredits(): self
    {
        return new self('เครดิตคงเหลือไม่พอ กรุณาเติมแพ็กเกจก่อน', 'no_credits');
    }

    public static function notCancellable(): self
    {
        return new self('การจองนี้ยกเลิกไม่ได้แล้ว', 'not_cancellable');
    }

    public static function branchMismatch(): self
    {
        return new self('เทรนเนอร์กับลูกทีมต้องอยู่สาขาเดียวกับรอบที่จอง', 'branch_mismatch');
    }
}
