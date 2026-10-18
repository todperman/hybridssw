<div x-data="{ showPassword: false }">
    <div class="fade-up">
        <h2 class="font-display text-3xl font-extrabold heading-th text-ink">สมัครสมาชิก</h2>
        <p class="mt-2 text-[15px] text-muted">
            กรอกข้อมูลครั้งเดียว แล้วจองยิมในเวลาที่สะดวกได้เลย
        </p>
    </div>

    <form wire:submit="register" class="mt-8 space-y-4">

        @if ($this->showsBranchSelector())
            <div class="field fade-up d-1">
                <select wire:model="branch_id" id="branch_id" class="peer field-input appearance-none">
                    @foreach ($this->branches() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <label for="branch_id" class="field-label !top-3.5 !text-[11px] !font-medium">สาขา</label>
            </div>
            @error('branch_id') <p class="field-error">{{ $message }}</p> @enderror
        @endif

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

            <div class="field fade-up d-2">
                <input wire:model.blur="nickname" id="nickname" type="text" placeholder=" " autocomplete="nickname"
                       class="peer field-input @error('nickname') border-red-400 @enderror">
                <label for="nickname" class="field-label">ชื่อเล่น (ไม่บังคับ)</label>
                @error('nickname') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            {{-- เพศเป็นตัวเลือก ไม่บังคับ ใช้จัดกลุ่มและให้เทรนเนอร์รู้จักลูกเทรน --}}
            <div class="field fade-up d-2">
                <select wire:model="gender" id="gender" class="peer field-input appearance-none @error('gender') border-red-400 @enderror">
                    <option value="">ไม่ระบุ</option>
                    @foreach (\App\Models\Member::GENDERS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label for="gender" class="field-label !top-3.5 !text-[11px] !font-medium">เพศ (ไม่บังคับ)</label>
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-muted">@svg('lucide-chevron-down', 'h-4 w-4', ['stroke-width' => '2'])</span>
                @error('gender') <p class="field-error">{{ $message }}</p> @enderror
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
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="field fade-up d-2">
                <input wire:model="password" id="password" placeholder=" " required autocomplete="new-password"
                       x-bind:type="showPassword ? 'text' : 'password'"
                       class="peer field-input pr-12 @error('password') border-red-400 @enderror">
                <label for="password" class="field-label">รหัสผ่าน</label>

                <button type="button" @click="showPassword = ! showPassword" class="field-toggle"
                        x-bind:aria-label="showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'">
                    @svg('lucide-eye', '', ['x-show' => '! showPassword', 'style' => 'width:18px;height:18px', 'stroke-width' => '1.8'])
                    @svg('lucide-eye-off', '', ['x-show' => 'showPassword', 'style' => 'width:18px;height:18px', 'stroke-width' => '1.8'])
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

        {{-- บุคลากร รพ. ใช้ฟรีได้ แต่ต้องให้แอดมินตรวจก่อน ตอนสมัครแค่บอกไว้ --}}
        <div class="fade-up d-5 rounded-2xl border border-line bg-white/60 p-4" x-data="{ staff: @entangle('hospital_staff') }">
            <label class="flex cursor-pointer items-start gap-3 text-[13.5px] leading-relaxed text-ink">
                <input type="checkbox" wire:model="hospital_staff" x-model="staff" class="mt-0.5 h-5 w-5 shrink-0 rounded border-line text-brand-deep focus:ring-brand-deep/30">
                <span>
                    <span class="font-medium">ฉันเป็นบุคลากรหรือแพทย์ของโรงพยาบาลศรีสวรรค์</span>
                    <span class="block text-[12.5px] text-muted">แอดมินตรวจสอบแล้วจะได้สิทธิ์ใช้ยิมฟรีตามเงื่อนไข</span>
                </span>
            </label>
            <div x-show="staff" x-cloak class="field mt-3">
                <input wire:model.blur="staff_reference" id="staff_reference" type="text" placeholder=" " autocomplete="off"
                       class="peer field-input">
                <label for="staff_reference" class="field-label">รหัสพนักงาน หรือเลขที่ใบประกอบวิชาชีพ</label>
                @error('staff_reference') <p class="field-error">{{ $message }}</p> @enderror
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

            <span wire:loading.remove wire:target="register" class="relative">สมัครสมาชิก</span>

            <span wire:loading wire:target="register" class="relative flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                </svg>
                กำลังสมัคร…
            </span>
        </button>
    </form>

    <p class="fade-up d-6 mt-6 text-center text-[13px] text-muted">
        เป็นเทรนเนอร์?
        <a href="{{ route('trainer.register') }}" wire:navigate class="font-medium text-brand-deep hover:underline">สมัครเป็นเทรนเนอร์ที่นี่</a>
    </p>
</div>
