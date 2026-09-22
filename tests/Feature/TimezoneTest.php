<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * เวลาเป็นเรื่องเป็นเรื่องตายของระบบจอง ถ้าเขตเวลาเพี้ยน รอบที่เลยเวลาไปแล้ว
 * จะยังจองได้ และรอบที่ยังไม่ถึงจะถูกมองว่าผ่านไปแล้ว
 */
class TimezoneTest extends TestCase
{
    #[Test]
    public function the_timezone_comes_from_the_environment(): void
    {
        // เคยเขียน 'UTC' ตายตัวไว้ใน config/app.php ทำให้ APP_TIMEZONE ใน .env ไม่มีผล
        $this->assertSame('Asia/Bangkok', config('app.timezone'));
        $this->assertSame('Asia/Bangkok', date_default_timezone_get());
    }

    #[Test]
    public function now_follows_the_configured_timezone(): void
    {
        $this->assertSame('Asia/Bangkok', now()->timezoneName);
        $this->assertSame(7 * 60, now()->utcOffset(), 'ไทยเร็วกว่า UTC 7 ชั่วโมงเสมอ ไม่มี daylight saving');
    }
}
