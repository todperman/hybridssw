<?php

use App\Http\Middleware\EnsureMemberIsApproved;
use App\Http\Middleware\EnsureUserIsTrainer;
use App\Livewire\MemberRegistration;
use App\Livewire\Reservations\BookingWizard;
use App\Livewire\Reservations\ReservationDetail;
use App\Livewire\Reservations\ReservationList;
use App\Livewire\TeamJoin;
use App\Livewire\Trainer\TeamRoster;
use App\Livewire\TrainerRegistration;
use Illuminate\Support\Facades\Route;

// หน้าแรกพาไปหน้าเข้าสู่ระบบเลย คนที่ล็อกอินอยู่แล้วไปหน้าของบทบาทตัวเอง
// หน้า landing เดิมยังอยู่ที่ resources/views/welcome.blade.php เปิดกลับได้ด้วย Route::view
Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
)->name('home');

// คนทั่วไปสมัครสมาชิกเอง แล้วจองยิมเอง (ปิดได้ด้วย GYM_REGISTRATION_PUBLIC=false)
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
            $user->trainer->isApproved() ? 'trainer.jobs' : 'trainer.pending'
        ),
        $user->member !== null => redirect()->route('member.reservations'),
        default => view('dashboard'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', EnsureUserIsTrainer::class])->prefix('trainer')->name('trainer.')->group(function () {
    // หน้ารอผลอนุมัติ ต้องอยู่นอกการกันของ middleware ไม่งั้นจะ redirect วนหาตัวเอง
    Route::get('pending', \App\Livewire\Trainer\PendingApproval::class)->name('pending');

    Route::get('jobs', ReservationList::class)->name('jobs');
    Route::get('book', BookingWizard::class)->name('book');
    Route::get('availability', \App\Livewire\Trainer\AvailabilityPlanner::class)->name('availability');
    Route::get('team', TeamRoster::class)->name('team');
});

Route::middleware(['auth', EnsureMemberIsApproved::class])->prefix('member')->name('member.')->group(function () {
    // หน้าสถานะการสมัคร middleware ปล่อยผ่านหน้านี้หน้าเดียวตอนยังไม่อนุมัติ
    Route::get('pending', \App\Livewire\Member\PendingApproval::class)->name('pending');

    Route::get('book', BookingWizard::class)->name('book');
    Route::get('reservations', ReservationList::class)->name('reservations');
});

// หน้าการจองหนึ่งรายการ ใช้ร่วมกันทุกบทบาท คอมโพเนนต์ตรวจเองว่าผู้ชมเกี่ยวข้องกับการจองนี้
Route::get('reservations/{reference}', ReservationDetail::class)
    ->middleware('auth')
    ->name('reservations.show');

Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// Omise แจ้งผลชำระ ยกเว้น CSRF ไว้ใน bootstrap/app.php
Route::post('webhooks/omise', \App\Http\Controllers\OmiseWebhookController::class)->name('webhooks.omise');

require __DIR__.'/auth.php';
