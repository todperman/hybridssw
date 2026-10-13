<?php

use Illuminate\Support\Facades\Schedule;

// ปิดรายการรอชำระที่เลยเวลาและคืนช่วงเวลาให้คนอื่นจองได้
// การจองใหม่ก็ปล่อยรายการค้างที่ขวางทางเองอยู่แล้ว job นี้มีไว้ให้สถานะในหน้าเว็บตรงกับความจริง
// และแจ้งผู้ชำระเงินว่ารายการหมดอายุ
Schedule::command('reservations:expire-holds')
    ->everyMinute()
    ->withoutOverlapping();

// ตาข่ายรองรับเมื่อ webhook ของ Omise ไม่มา ถามผลรายการที่ค้างอยู่จาก Omise โดยตรง
Schedule::command('payments:sync-omise')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->when(fn () => config('gym.payments.driver') === 'omise');
