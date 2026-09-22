<?php

namespace App\Exceptions;

use Exception;

/** ข้อผิดพลาดเชิงกติกาของกลุ่มลูกทีม ข้อความพร้อมแสดงให้ผู้ใช้เห็นได้เลย */
class MemberGroupException extends Exception
{
    public function __construct(string $message, public readonly string $reason = 'invalid')
    {
        parent::__construct($message);
    }

    public static function groupFull(int $capacity): self
    {
        return new self("กลุ่มนี้รับได้สูงสุด {$capacity} คน ซึ่งเต็มแล้ว", 'group_full');
    }

    public static function notInTeam(): self
    {
        return new self('ลูกทีมคนนี้ไม่ได้อยู่ในทีมของคุณ', 'not_in_team');
    }

    public static function alreadyInGroup(): self
    {
        return new self('ลูกทีมคนนี้อยู่ในกลุ่มนี้แล้ว', 'already_in_group');
    }

    public static function notOwner(): self
    {
        return new self('กลุ่มนี้ไม่ใช่ของคุณ', 'not_owner');
    }

    public static function duplicateName(string $name): self
    {
        return new self("คุณมีกลุ่มชื่อ \"{$name}\" อยู่แล้ว", 'duplicate_name');
    }

    public static function branchMismatch(): self
    {
        return new self('ลูกทีมกับกลุ่มต้องอยู่สาขาเดียวกัน', 'branch_mismatch');
    }
}
