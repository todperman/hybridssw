<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function layout(): array
    {
        return [
            'eyebrow' => 'ยินดีต้อนรับกลับ',
            'heading' => 'พร้อมเทรน',
            'headingAccent' => 'อีกครั้งแล้วใช่ไหม',
            'lead' => 'เข้าสู่ระบบเพื่อจองยิม ดูการจองของคุณ หรือจัดการงานสำหรับ Trainer',
            'points' => [
                'จองทั้งยิมเป็นรายชั่วโมง ได้ใช้เฉพาะกลุ่มของคุณ',
                'เห็นเฉพาะเวลาที่ยิมและ Trainer ว่างจริง',
                'Trainer จองให้ลูกเทรนทั้งกลุ่มได้ในครั้งเดียว',
            ],
            'altHref' => \App\Support\RegistrationRules::publicRegistrationOpen() ? route('register') : route('trainer.register'),
            'altLabel' => 'ยังไม่มีบัญชี',
            'altCta' => \App\Support\RegistrationRules::publicRegistrationOpen() ? 'สมัครสมาชิก' : 'สมัครเทรนเนอร์',
        ];
    }

    public function rendering(\Illuminate\View\View $view): void
    {
        $view->layout('layouts.auth', $this->layout());
    }
}; ?>

<div x-data="{ showPassword: false }">
    <div class="fade-up">
        <h2 class="font-display text-3xl font-extrabold heading-th text-ink">เข้าสู่ระบบ</h2>
        <p class="mt-2 text-[15px] text-muted">กรอกอีเมลและรหัสผ่านที่ลงทะเบียนไว้</p>
    </div>

    <x-auth-session-status class="fade-up d-1 mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-4">

        <div class="field fade-up d-1">
            <input wire:model="form.email" id="email" type="email" name="email" placeholder=" "
                   required autofocus autocomplete="username"
                   class="peer field-input @error('form.email') border-red-400 focus:border-red-500 focus:ring-red-500/10 @enderror">
            <label for="email" class="field-label">อีเมล</label>

            @error('form.email')
                <p class="field-error">
                    @svg('lucide-circle-alert', 'mt-0.5 h-3.5 w-3.5 shrink-0')
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div class="field fade-up d-2">
            <input wire:model="form.password" id="password" name="password" placeholder=" "
                   required autocomplete="current-password"
                   x-bind:type="showPassword ? 'text' : 'password'"
                   class="peer field-input pr-12 @error('form.password') border-red-400 focus:border-red-500 focus:ring-red-500/10 @enderror">
            <label for="password" class="field-label">รหัสผ่าน</label>

            <button type="button" @click="showPassword = ! showPassword"
                    class="field-toggle"
                    x-bind:aria-label="showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'">
                @svg('lucide-eye', 'h-4.5 w-4.5', ['x-show' => '! showPassword', 'style' => 'width:18px;height:18px', 'stroke-width' => '1.8'])
                @svg('lucide-eye-off', 'h-4.5 w-4.5', ['x-show' => 'showPassword', 'style' => 'width:18px;height:18px', 'stroke-width' => '1.8'])
            </button>

            @error('form.password')
                <p class="field-error">
                    @svg('lucide-circle-alert', 'mt-0.5 h-3.5 w-3.5 shrink-0')
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div class="fade-up d-3 flex items-center justify-between pt-1">
            <label for="remember" class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-muted">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                       class="h-4 w-4 rounded border-line text-brand-deep focus:ring-brand-deep/30">
                จดจำฉันไว้
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate
                   class="text-sm text-brand-deep transition hover:text-brand-dark hover:underline">ลืมรหัสผ่าน</a>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="fade-up d-4 group relative mt-2 flex w-full items-center justify-center gap-2 overflow-hidden
                       rounded-2xl bg-brand-dark px-6 py-3.5 text-[15px] font-semibold text-white shadow-soft
                       transition duration-300 ease-out-soft hover:bg-brand-deep hover:shadow-lift
                       focus:outline-none focus:ring-4 focus:ring-brand-deep/25 disabled:opacity-60">
            {{-- แถบแสงวิ่งผ่านตอน hover --}}
            <span class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent transition-transform duration-700 group-hover:translate-x-full"></span>

            <span wire:loading.remove wire:target="login" class="relative">เข้าสู่ระบบ</span>

            <span wire:loading wire:target="login" class="relative flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                </svg>
                กำลังเข้าสู่ระบบ…
            </span>
        </button>
    </form>

    {{-- หน้าแรกพามาที่หน้านี้ จึงต้องมีทางไปสมัครครบทุกแบบตรงนี้ ไม่ใช่แค่ปุ่มมุมบน --}}
    <div class="fade-up d-5 mt-8 border-t border-line pt-6 text-center">
        <p class="text-sm text-muted">ยังไม่มีบัญชี</p>

        <div class="mt-3 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
            @if (\App\Support\RegistrationRules::publicRegistrationOpen())
                <a href="{{ route('register') }}" wire:navigate class="btn-ghost justify-center px-6 py-3">
                    @svg('lucide-user-plus', 'h-4 w-4', ['stroke-width' => '1.9'])
                    สมัครสมาชิก
                </a>
            @endif

            <a href="{{ route('trainer.register') }}" wire:navigate class="btn-ghost justify-center px-6 py-3">
                @svg('lucide-users', 'h-4 w-4', ['stroke-width' => '1.9'])
                สมัครเป็นเทรนเนอร์
            </a>
        </div>
    </div>
</div>
