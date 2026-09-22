<?php

use Illuminate\Support\Facades\Schedule;

// เลื่อนขอบเขตการจองไปข้างหน้าทุกวันตอนตีสอง
Schedule::command('sessions:generate')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// คืนที่นั่งของคิวสำรองที่เลื่อนขึ้นแล้วไม่ยืนยัน ต้องถี่พอจะคืนสิทธิ์ให้คนถัดไปทัน
Schedule::command('bookings:release-unconfirmed')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// สรุปผลคาบที่จบแล้วและบันทึกผู้ที่ไม่มาตามนัด
Schedule::command('sessions:finalize')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
