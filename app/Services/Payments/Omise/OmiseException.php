<?php

namespace App\Services\Payments\Omise;

use RuntimeException;

/** Omise ตอบกลับเป็นข้อผิดพลาด หรือติดต่อไม่ได้ ข้อความในนี้แสดงให้ผู้ใช้เห็นได้ */
class OmiseException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $omiseCode = null, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
