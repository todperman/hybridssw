<?php

use App\Http\Middleware\EnsureMemberIsApproved;
use App\Http\Middleware\EnsureUserIsTrainer;
use App\Livewire\Member\MyBookings;
use App\Livewire\Member\Schedule as MemberSchedule;
use App\Livewire\MemberRegistration;
use App\Livewire\TeamJoin;
use App\Livewire\Trainer\BookingBoard;
use App\Livewire\Trainer\Insights;
use App\Livewire\Trainer\TeamRoster;
use App\Livewire\TrainerRegistration;
use Illuminate\Support\Facades\Route;

// หน้าแรกพาไปหน้าเข้าสู่ระบบเลย คนที่ล็อกอินอยู่แล้วไปหน้าของบทบาทตัวเอง
// หน้า landing เดิมยังอยู่ที่ resources/views/welcome.blade.php เปิดกลับได้ด้วย Route::view
Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
)->name('home');

// คนทั่วไปสมัครสมาชิกเอง แล้วจองรอบให้ตัวเอง (ปิดได้ด้วย GYM_REGISTRATION_PUBLIC=false)
Route::get('register', MemberRegistration::class)
    ->middleware('guest')
    ->name('register');

// สมัครเป็นเทรนเนอร์ (ภายในอนุมัติอัตโนมัติ ภายนอกรอแอดมินตรวจ)
Route::get('register/trainer', TrainerRegistration::class)
    ->middleware('guest')
    ->name('trainer.register');

// ลิงก์/QR ที่เทรนเนอร์แชร์ให้ลูกทีมสมัครเข้าทีมเอง
Route::get('team/join/{token}', TeamJoin::class)
    ->middleware('guest')
    ->name('team.join');

// พาผู้ใช้ไปหน้าที่ตรงกับบทบาทของตัวเอง
// เช็คว่ามีโปรไฟล์จริงด้วย ไม่ใช่ดูแค่ role เพราะผู้ใช้ที่ยังไม่มีโปรไฟล์
// จะถูกส่งไปหน้าที่พังทันที
Route::get('dashboard', function () {
    $user = auth()->user();

    return match (true) {
        $user->role?->canAccessAdminPanel() => redirect('/admin'),
        $user->trainer !== null => redirect()->route(
            $user->trainer->isApproved() ? 'trainer.schedule' : 'trainer.pending'
        ),
        $user->member !== null => redirect()->route('member.bookings'),
        default => view('dashboard'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', EnsureUserIsTrainer::class])->prefix('trainer')->name('trainer.')->group(function () {
    // หน้ารอผลอนุมัติ ต้องอยู่นอกการกันของ middleware ไม่งั้นจะ redirect วนหาตัวเอง
    Route::get('pending', \App\Livewire\Trainer\PendingApproval::class)->name('pending');

    Route::get('schedule', BookingBoard::class)->name('schedule');
    Route::get('team', TeamRoster::class)->name('team');
    Route::get('insights', Insights::class)->name('insights');
});

Route::middleware(['auth', EnsureMemberIsApproved::class])->prefix('member')->name('member.')->group(function () {
    // หน้าสถานะการสมัคร middleware ปล่อยผ่านหน้านี้หน้าเดียวตอนยังไม่อนุมัติ
    Route::get('pending', \App\Livewire\Member\PendingApproval::class)->name('pending');

    Route::get('schedule', MemberSchedule::class)->name('schedule');
    Route::get('bookings', MyBookings::class)->name('bookings');
});

Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

require __DIR__.'/auth.php';
