<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    // ไม่มีหน้าสมัครแบบทั่วไป เพราะจะได้บัญชีที่ไม่มีทั้งโปรไฟล์เทรนเนอร์และลูกทีม
    // ซึ่งเข้ามาแล้วทำอะไรไม่ได้
    // เทรนเนอร์สมัครที่ /register/trainer ส่วนลูกทีมเข้าผ่านลิงก์ชวนของเทรนเนอร์

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
