<div x-data="{ showPassword: false }">
    <div class="fade-up">
        <h2 class="font-display text-3xl font-extrabold heading-th text-ink">สมัครเข้าทีม</h2>
        <p class="mt-2 text-[15px] text-muted">
            กรอกข้อมูลครั้งเดียว จากนั้น {{ $trainer->user->name }} จะจองรอบให้คุณได้เลย
        </p>
    </div>

    <form wire:submit="join" class="mt-8 space-y-4">

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field fade-up d-1">
                <input wire:model.blur="first_name" id="first_name" type="text" placeholder=" " required autofocus autocomplete="given-name"
                       class="peer field-input @error('first_name') border-red-400 @enderror">
                <label for="first_name" class="field-label">ชื่อ</label>
                @error('first_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field fade-up d-1">
                <input wire:model.blur="last_name" id="last_name" type="text" placeholder=" " required autocomplete="family-name"
                       class="peer field-input @error('last_name') border-red-400 @enderror">
                <label for="last_name" class="field-label">นามสกุล</label>
                @error('last_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field fade-up d-2 sm:col-span-2">
                <input wire:model.blur="nickname" id="nickname" type="text" placeholder=" " autocomplete="nickname"
                       class="peer field-input @error('nickname') border-red-400 @enderror">
                <label for="nickname" class="field-label">ชื่อเล่น (ไม่บังคับ)</label>
                @error('nickname') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field fade-up d-2">
                <input wire:model.blur="phone" id="phone" type="tel" placeholder=" " required autocomplete="tel"
                       class="peer field-input @error('phone') border-red-400 @enderror">
                <label for="phone" class="field-label">เบอร์โทร</label>
                @error('phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field fade-up d-2">
                <input wire:model.blur="email" id="email" type="email" placeholder=" " required autocomplete="username"
                       class="peer field-input @error('email') border-red-400 @enderror">
                <label for="email" class="field-label">อีเมล</label>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field fade-up d-2">
                <input wire:model="password" id="password" placeholder=" " required autocomplete="new-password"
                       x-bind:type="showPassword ? 'text' : 'password'"
                       class="peer field-input pr-12 @error('password') border-red-400 @enderror">
                <label for="password" class="field-label">รหัสผ่าน</label>

                <button type="button" @click="showPassword = ! showPassword" class="field-toggle"
                        x-bind:aria-label="showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'">
                    <x-lucide-eye x-show="! showPassword" style="width:18px;height:18px" stroke-width="1.8" />
                    <x-lucide-eye-off x-show="showPassword" style="width:18px;height:18px" stroke-width="1.8" />
                </button>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field fade-up d-2">
                <input wire:model="password_confirmation" id="password_confirmation" placeholder=" " required autocomplete="new-password"
                       x-bind:type="showPassword ? 'text' : 'password'"
                       class="peer field-input">
                <label for="password_confirmation" class="field-label">ยืนยันรหัสผ่าน</label>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field fade-up d-3">
                <input wire:model.blur="emergency_contact_name" id="emergency_contact_name" type="text" placeholder=" " required
                       class="peer field-input @error('emergency_contact_name') border-red-400 @enderror">
                <label for="emergency_contact_name" class="field-label">ผู้ติดต่อฉุกเฉิน</label>
                @error('emergency_contact_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field fade-up d-3">
                <input wire:model.blur="emergency_contact_phone" id="emergency_contact_phone" type="tel" placeholder=" " required
                       class="peer field-input @error('emergency_contact_phone') border-red-400 @enderror">
                <label for="emergency_contact_phone" class="field-label">เบอร์ผู้ติดต่อฉุกเฉิน</label>
                @error('emergency_contact_phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="fade-up d-5 flex cursor-pointer items-start gap-3 text-[13.5px] leading-relaxed text-muted">
            <input type="checkbox" wire:model="accept" class="mt-0.5 h-5 w-5 shrink-0 rounded border-line text-brand-deep focus:ring-brand-deep/30">
            <span>ข้าพเจ้ายืนยันว่าข้อมูลข้างต้นเป็นความจริง และรับทราบความเสี่ยงจากการออกกำลังกาย</span>
        </label>
        @error('accept') <p class="field-error">{{ $message }}</p> @enderror

        <button type="submit" wire:loading.attr="disabled"
                class="fade-up d-6 group relative mt-2 flex w-full items-center justify-center gap-2 overflow-hidden
                       rounded-2xl bg-brand-dark px-6 py-3.5 text-[15px] font-semibold text-white shadow-soft
                       transition duration-300 ease-out-soft hover:bg-brand-deep hover:shadow-lift
                       focus:outline-none focus:ring-4 focus:ring-brand-deep/25 disabled:opacity-60">
            <span class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent transition-transform duration-700 group-hover:translate-x-full"></span>

            <span wire:loading.remove wire:target="join" class="relative">เข้าร่วมทีม</span>

            <span wire:loading wire:target="join" class="relative flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                </svg>
                กำลังสมัคร…
            </span>
        </button>
    </form>
</div>
