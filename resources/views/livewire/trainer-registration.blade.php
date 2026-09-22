<div x-data="{
        showPassword: false,
        pw: '',
        /* วัดความแข็งแรงรหัสผ่านฝั่งเบราว์เซอร์ เพื่อให้เห็นผลทันทีขณะพิมพ์ */
        get score() {
            let s = 0;
            if (this.pw.length >= 8) s++;
            if (this.pw.length >= 12) s++;
            if (/[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw)) s++;
            if (/[0-9]/.test(this.pw)) s++;
            if (/[^A-Za-z0-9]/.test(this.pw)) s++;
            return Math.min(s, 4);
        },
        get label() {
            return ['', 'อ่อนมาก', 'พอใช้', 'ดี', 'แข็งแรงมาก'][this.score] || '';
        },
        get tone() {
            return ['bg-line', 'bg-red-400', 'bg-accent', 'bg-brand', 'bg-brand-deep'][this.score];
        },
     }">

    {{-- ตัวบอกขั้นตอน --}}
    <ol class="fade-up flex items-center gap-2 sm:gap-3">
        @foreach ($this->steps() as $number => $meta)
            @php($done = $number < $step)
            @php($current = $number === $step)

            {{-- ขั้นสุดท้ายไม่ต้อง flex-1 ไม่งั้นมันจะกินพื้นที่หนึ่งส่วนแล้วเหลือช่องว่างท้ายแถว
                 ปล่อยให้เส้นเชื่อมเป็นตัวยืดแทน จุดสุดท้ายจึงไปชิดขอบขวาพอดี --}}
            <li class="flex items-center gap-2.5 {{ $loop->last ? 'shrink-0' : 'flex-1' }}">
                <button type="button" wire:click="goToStep({{ $number }})" @disabled($number >= $step)
                        class="flex min-h-[44px] items-center gap-2.5 py-1.5 text-start {{ $done ? 'cursor-pointer' : 'cursor-default' }}">
                    <span class="step-dot
                        {{ $current ? 'bg-brand-dark text-white shadow-soft ring-4 ring-brand-deep/15' : '' }}
                        {{ $done ? 'bg-brand-light text-brand-dark' : '' }}
                        {{ ! $current && ! $done ? 'bg-mist text-muted' : '' }}">
                        @if ($done)
                            <x-lucide-check class="h-4 w-4" stroke-width="2.5" />
                        @else
                            {{ $number }}
                        @endif
                    </span>

                    <span class="hidden sm:block">
                        <span class="block text-[13px] font-medium leading-tight {{ $current ? 'text-ink' : 'text-muted' }}">{{ $meta['title'] }}</span>
                        <span class="block text-[11px] leading-tight text-muted/70">{{ $meta['hint'] }}</span>
                    </span>
                </button>

                @if (! $loop->last)
                    <span class="h-px flex-1 rounded-full {{ $done ? 'bg-brand-light' : 'bg-line' }} transition-colors duration-500"></span>
                @endif
            </li>
        @endforeach
    </ol>

    <div class="mt-8" wire:key="step-{{ $step }}">

        {{-- ขั้นที่ 1: บัญชี --}}
        @if ($step === 1)
            <div class="fade-up">
                <h2 class="font-display text-3xl font-extrabold heading-th text-ink">สมัครเป็นเทรนเนอร์</h2>
                <p class="mt-2 text-[15px] text-muted">เริ่มจากข้อมูลติดต่อและรหัสผ่านของคุณ</p>
            </div>

            <form wire:submit="next" class="mt-7 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field fade-up d-1">
                        <input wire:model.blur="first_name" id="first_name" type="text" placeholder=" " autofocus autocomplete="given-name"
                               class="peer field-input @error('first_name') border-red-400 @enderror">
                        <label for="first_name" class="field-label">ชื่อ</label>
                        @error('first_name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field fade-up d-1">
                        <input wire:model.blur="last_name" id="last_name" type="text" placeholder=" " autocomplete="family-name"
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
                        <input wire:model.blur="phone" id="phone" type="tel" placeholder=" " autocomplete="tel"
                               class="peer field-input @error('phone') border-red-400 @enderror">
                        <label for="phone" class="field-label">เบอร์โทร</label>
                        @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field fade-up d-2">
                        <input wire:model.blur="email" id="email" type="email" placeholder=" " autocomplete="username"
                               class="peer field-input @error('email') border-red-400 @enderror">
                        <label for="email" class="field-label">อีเมล</label>
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field fade-up d-3">
                        <input wire:model="password" x-model="pw" id="password" placeholder=" " autocomplete="new-password"
                               x-bind:type="showPassword ? 'text' : 'password'"
                               class="peer field-input pr-12 @error('password') border-red-400 @enderror">
                        <label for="password" class="field-label">รหัสผ่าน</label>

                        <button type="button" @click="showPassword = ! showPassword"
                                class="field-toggle"
                                x-bind:aria-label="showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'">
                            <x-lucide-eye x-show="! showPassword" style="width:18px;height:18px" stroke-width="1.8" />
                            <x-lucide-eye-off x-show="showPassword" style="width:18px;height:18px" stroke-width="1.8" />
                        </button>
                    </div>

                    <div class="field fade-up d-3">
                        <input wire:model="password_confirmation" id="password_confirmation" placeholder=" " autocomplete="new-password"
                               x-bind:type="showPassword ? 'text' : 'password'"
                               class="peer field-input">
                        <label for="password_confirmation" class="field-label">ยืนยันรหัสผ่าน</label>
                    </div>
                </div>

                {{-- แถบความแข็งแรงของรหัสผ่าน --}}
                <div class="fade-up d-4" x-show="pw.length > 0" x-cloak>
                    <div class="flex gap-1.5">
                        <template x-for="i in 4" :key="i">
                            <span class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                                  :class="i <= score ? tone : 'bg-line'"></span>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[12px] text-muted">ความแข็งแรงรหัสผ่าน: <span class="font-medium text-ink" x-text="label"></span></p>
                </div>

                @error('password') <p class="field-error">{{ $message }}</p> @enderror

                <button type="submit" class="btn-primary fade-up d-5 mt-2 w-full px-6 py-3.5 text-[15px]">
                    ถัดไป
                    <x-lucide-arrow-right class="h-4 w-4" />
                </button>
            </form>
        @endif

        {{-- ขั้นที่ 2: ประเภทและสาขา --}}
        @if ($step === 2)
            <div class="fade-up">
                <h2 class="font-display text-3xl font-extrabold heading-th text-ink">คุณเป็นเทรนเนอร์แบบไหน</h2>
                <p class="mt-2 text-[15px] text-muted">ประเภทที่เลือกจะกำหนดโควตาและขั้นตอนอนุมัติของคุณ</p>
            </div>

            <form wire:submit="{{ $this->isLastStep() ? 'register' : 'next' }}" class="mt-7 space-y-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Enums\TrainerType::orderedForRegistration() as $case)
                        @php($active = $type === $case->value)

                        <label class="choice-card fade-up d-{{ $loop->index + 1 }} {{ $active ? 'choice-card-active' : '' }}">
                            <input type="radio" wire:model.live="type" value="{{ $case->value }}" class="sr-only">

                            <span class="absolute right-4 top-4 grid h-5 w-5 place-items-center rounded-full border-2 transition
                                         {{ $active ? 'border-brand-deep bg-brand-deep' : 'border-line' }}">
                                @if ($active)
                                    <x-lucide-check class="h-3 w-3 text-white" stroke-width="3.5" />
                                @endif
                            </span>

                            <span class="block font-display text-[17px] font-bold text-ink">{{ $case->label() }}</span>

                            <span class="mt-1 block text-[13px] {{ $case->isAutoApproved() ? 'text-brand-deep' : 'text-accent-ink' }}">
                                {{ $case->isAutoApproved() ? 'ใช้งานได้ทันทีหลังสมัคร' : 'ต้องรอแอดมินตรวจเอกสาร' }}
                            </span>

                            <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-[13px]">
                                <div class="flex justify-between"><dt class="text-muted">ที่นั่งต่อรอบ</dt><dd class="font-medium text-ink">{{ $case->defaultMaxSeatsPerSession() }}</dd></div>
                                <div class="flex justify-between"><dt class="text-muted">จองล่วงหน้า</dt><dd class="font-medium text-ink">{{ $case->defaultAdvanceBookingDays() }} วัน</dd></div>

                            </dl>
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="field-error">{{ $message }}</p> @enderror

                @if ($this->showsBranchSelector())
                    <div class="field fade-up d-3">
                        <select wire:model="branch_id" id="branch_id" class="peer field-input appearance-none">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <label for="branch_id" class="field-label !top-3.5 !text-[11px] !font-medium">สาขาที่ประจำ</label>
                    </div>
                    @error('branch_id') <p class="field-error">{{ $message }}</p> @enderror
                @endif

                @if ($this->isLastStep())
                    {{-- ปิดการยืนยันตัวตนไว้ ขั้นนี้จึงเป็นขั้นสุดท้าย --}}
                    @include('livewire.partials.registration-summary')
                @endif

                <div class="fade-up d-5 flex gap-3">
                    <button type="button" wire:click="back" class="btn-ghost px-6 py-3.5">ย้อนกลับ</button>

                    <button type="submit" wire:loading.attr="disabled" class="btn-primary flex-1 px-6 py-3.5 text-[15px]">
                        @if ($this->isLastStep())
                            <span wire:loading.remove wire:target="register">ยืนยันสมัครเทรนเนอร์</span>
                            <span wire:loading wire:target="register">กำลังสมัคร…</span>
                        @else
                            ถัดไป
                            <x-lucide-arrow-right class="h-4 w-4" />
                        @endif
                    </button>
                </div>
            </form>
        @endif

        {{-- ขั้นที่ 3: ยืนยันตัวตน --}}
        @if ($step === 3)
            <div class="fade-up">
                <h2 class="font-display text-3xl font-extrabold heading-th text-ink">
                    {{ $this->isInternal() ? 'ยืนยันว่าเป็นคนภายใน' : 'ข้อมูลใบรับรอง' }}
                </h2>
                <p class="mt-2 text-[15px] text-muted">
                    {{ $this->isInternal()
                        ? 'กรอกรหัสพนักงานที่ได้รับจากแอดมิน'
                        : 'กรอกเท่าที่มี ไม่บังคับ แอดมินจะขอดูเอกสารจริงตอนพิจารณาอนุมัติ' }}
                </p>
            </div>

            <form wire:submit="register" class="mt-7 space-y-4">
                @if ($this->isInternal())
                    <div class="field fade-up d-1">
                        <input wire:model="internal_code" id="internal_code" type="text" placeholder=" " autofocus
                               class="peer field-input font-mono @error('internal_code') border-red-400 @enderror">
                        <label for="internal_code" class="field-label">รหัสพนักงาน</label>
                        @error('internal_code') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="field fade-up d-1">
                        <input wire:model.blur="certification_name" id="certification_name" type="text" placeholder=" " autofocus
                               class="peer field-input @error('certification_name') border-red-400 @enderror">
                        <label for="certification_name" class="field-label">ชื่อใบรับรอง (ไม่บังคับ)</label>
                        @error('certification_name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field fade-up d-2">
                        <input wire:model.blur="certification_expires_at" id="certification_expires_at" type="date" placeholder=" "
                               class="peer field-input @error('certification_expires_at') border-red-400 @enderror">
                        <label for="certification_expires_at" class="field-label !top-3.5 !text-[11px] !font-medium">วันหมดอายุใบรับรอง (ไม่บังคับ)</label>
                        @error('certification_expires_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="field fade-up d-3">
                    <textarea wire:model="bio" id="bio" rows="3" placeholder=" " class="peer field-input resize-none"></textarea>
                    <label for="bio" class="field-label !top-5 !text-[11px] !font-medium">แนะนำตัว (ไม่บังคับ)</label>
                </div>

                @include('livewire.partials.registration-summary')

                <div class="fade-up d-5 flex gap-3">
                    <button type="button" wire:click="back" class="btn-ghost px-6 py-3.5">ย้อนกลับ</button>

                    <button type="submit" wire:loading.attr="disabled"
                            class="group relative flex flex-1 items-center justify-center gap-2 overflow-hidden rounded-2xl
                                   bg-brand-dark px-6 py-3.5 text-[15px] font-semibold text-white shadow-soft
                                   transition duration-300 ease-out-soft hover:bg-brand-deep hover:shadow-lift
                                   focus:outline-none focus:ring-4 focus:ring-brand-deep/25 disabled:opacity-60">
                        <span class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent transition-transform duration-700 group-hover:translate-x-full"></span>

                        <span wire:loading.remove wire:target="register" class="relative">ยืนยันสมัครเทรนเนอร์</span>

                        <span wire:loading wire:target="register" class="relative flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                            </svg>
                            กำลังสมัคร…
                        </span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
