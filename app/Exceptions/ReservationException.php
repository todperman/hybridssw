<?php

namespace App\Exceptions;

use RuntimeException;

/** เหตุผลที่จองหรือแก้การจองไม่ได้ ข้อความแสดงให้ผู้ใช้เห็นตรง ๆ */
class ReservationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid')
    {
        parent::__construct($message);
    }

    public static function invalidDuration(int $max): self
    {
        return new self("ระยะเวลาต้องอยู่ระหว่าง 1–{$max} ชั่วโมง", 'invalid_duration');
    }

    public static function inThePast(): self
    {
        return new self('เลือกเวลาที่ยังไม่ถึงเท่านั้น', 'in_the_past');
    }

    public static function beyondWindow(int $days): self
    {
        return new self("จองล่วงหน้าได้ไม่เกิน {$days} วัน", 'beyond_window');
    }

    public static function gymClosed(): self
    {
        return new self('ยิมไม่เปิดครบทุกชั่วโมงในช่วงที่เลือก', 'gym_closed');
    }

    public static function gymTaken(): self
    {
        return new self('ช่วงเวลานี้มีคนจองยิมไปแล้ว', 'gym_taken');
    }

    public static function groupSize(int $max): self
    {
        return new self("กลุ่มต้องมีผู้เข้าร่วม 1–{$max} คน ไม่รวม Trainer", 'group_size');
    }

    public static function bookerNotInGroup(): self
    {
        return new self('ผู้จองต้องอยู่ในรายชื่อผู้เข้าร่วมด้วย', 'booker_not_in_group');
    }

    public static function payerNotInGroup(): self
    {
        return new self('ผู้ชำระเงินต้องเป็นหนึ่งในผู้เข้าร่วม', 'payer_not_in_group');
    }

    public static function memberNotEligible(string $name): self
    {
        return new self("{$name} ยังเพิ่มในการจองไม่ได้ (ยังไม่อนุมัติหรือถูกระงับ)", 'member_not_eligible');
    }

    public static function memberBusy(string $name): self
    {
        return new self("{$name} มีการจองอื่นในช่วงเวลานี้แล้ว", 'member_busy');
    }

    public static function trainerRequired(): self
    {
        return new self('ต้องเลือก Trainer เว้นแต่ผู้เข้าร่วมได้รับสิทธิ์เข้าใช้โดยไม่มี Trainer', 'trainer_required');
    }

    public static function trainerUnavailable(): self
    {
        return new self('Trainer ไม่ว่างครบทุกชั่วโมงในช่วงที่เลือก', 'trainer_unavailable');
    }

    public static function notAllowed(): self
    {
        return new self('คุณไม่มีสิทธิ์ทำรายการนี้กับการจองนี้', 'not_allowed');
    }

    public static function notConfirmed(): self
    {
        return new self('ทำได้เฉพาะการจองที่ยืนยันแล้ว', 'not_confirmed');
    }

    public static function needsApproval(): self
    {
        return new self('เลยเส้นตายเลื่อนเองแล้ว (00.00 น. ของวันใช้งาน) ต้องส่งคำขอให้แอดมินอนุมัติ', 'needs_approval');
    }

    public static function requestPending(): self
    {
        return new self('มีคำขอที่รอแอดมินพิจารณาอยู่แล้ว', 'request_pending');
    }

    public static function alreadyPaid(): self
    {
        return new self('การจองนี้บันทึกรับชำระไปแล้ว', 'already_paid');
    }

    public static function amountMismatch(): self
    {
        return new self('ยอดชำระไม่ตรงกับยอดของการจอง', 'amount_mismatch');
    }

    public static function lockedByReservation(): self
    {
        return new self('มีการจองในช่วงเวลานี้แล้ว ลบไม่ได้ ต้องขอเลื่อนหรือขอเปลี่ยน Trainer แทน', 'locked_by_reservation');
    }
}
