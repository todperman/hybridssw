<div class="pb-16">
    @php($trainer = $this->trainer)
    @php($days = \App\Models\ScheduleTemplate::DAY_NAMES)
    @php($hours = $this->hourOptions())
    @php($order = [1, 2, 3, 4, 5, 6, 0])
    @php($short = [0 => 'อา.', 1 => 'จ.', 2 => 'อ.', 3 => 'พ.', 4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.'])

    <x-page-hero eyebrow="เวลาว่าง" title="ตั้งเวลาที่พร้อมรับงาน" pattern="stopwatch" pattern-alt="kettlebell"
                 subtitle="ลูกเทรนจะเห็นคุณเฉพาะช่วงที่ว่างครบทุกชั่วโมงของการจอง">
        <x-slot:stats>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="toggleAccepting" role="switch" aria-checked="{{ $trainer->accepts_bookings ? 'true' : 'false' }}"
                        class="inline-flex min-h-[44px] items-center gap-3 rounded-full border border-white/20 bg-white/10 py-1 pl-1 pr-4 text-sm font-medium text-paper backdrop-blur-sm">
                    <span class="relative h-7 w-12 rounded-full transition {{ $trainer->accepts_bookings ? 'bg-brand-light' : 'bg-white/25' }}">
                        <span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all {{ $trainer->accepts_bookings ? 'left-6' : 'left-1' }}"></span>
                    </span>
                    {{ $trainer->accepts_bookings ? 'เปิดรับงาน' : 'หยุดรับงานใหม่' }}
                </button>
            </div>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-6 sm:px-6">
        @if ($flash)
            <div class="card border-emerald-300 bg-emerald-50 px-5 py-3 text-sm text-emerald-800" role="status">{{ $flash }}</div>
        @endif
        @if ($error)
            <div class="card border-red-300 bg-red-50 px-5 py-3 text-sm text-red-700" role="alert">{{ $error }}</div>
        @endif

        {{-- ประจำสัปดาห์ --}}
        <section class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">เวลาว่างประจำสัปดาห์</h2>
            </div>

            <ul class="divide-y divide-line text-sm">
                @foreach ($order as $d)
                    <li class="flex items-start gap-3 px-5 py-3" wire:key="wd-{{ $d }}">
                        <span class="w-20 shrink-0 pt-1 font-medium text-ink">{{ $days[$d] }}</span>
                        <div class="flex flex-1 flex-wrap gap-2">
                            @forelse ($this->weekly->get($d, collect()) as $w)
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-light/30 py-1 pl-3 pr-1 text-brand-dark" wire:key="w-{{ $w->id }}">
                                    {{ substr($w->start_time, 0, 5) }}–{{ substr($w->end_time, 0, 5) }}
                                    <button type="button" wire:click="removeWindow({{ $w->id }})" class="grid h-6 w-6 place-items-center rounded-full hover:bg-white/60" aria-label="ลบช่วง {{ $w->label() }}">
                                        @svg('lucide-x', 'h-3.5 w-3.5')
                                    </button>
                                </span>
                            @empty
                                <span class="pt-1 text-muted">ไม่ว่าง</span>
                            @endforelse
                        </div>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="addWeekly" class="space-y-3 border-t border-line p-4 sm:p-5">
                <p class="text-sm font-medium text-ink">เพิ่มช่วงเวลา</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($order as $d)
                        <label class="cursor-pointer" wire:key="pick-{{ $d }}">
                            <input type="checkbox" wire:model="weekdays" value="{{ $d }}" class="peer sr-only">
                            <span class="inline-flex min-h-[40px] min-w-[44px] items-center justify-center rounded-full border border-line bg-white/60 px-3 text-sm text-ink transition peer-checked:border-brand-dark peer-checked:bg-brand-dark peer-checked:text-white">
                                {{ $short[$d] }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select wire:model="weeklyStart" aria-label="เวลาเริ่ม" class="min-h-[44px] rounded-xl border-line bg-white/70 text-sm">
                        @foreach ($hours as $h) <option value="{{ $h }}">{{ $h }}</option> @endforeach
                    </select>
                    <span class="text-muted">ถึง</span>
                    <select wire:model="weeklyEnd" aria-label="เวลาสิ้นสุด" class="min-h-[44px] rounded-xl border-line bg-white/70 text-sm">
                        @foreach ($hours as $h) <option value="{{ $h }}">{{ $h }}</option> @endforeach
                    </select>
                    <button type="submit" class="btn-primary px-4 py-2.5 text-sm">เพิ่ม</button>
                </div>
            </form>
        </section>

        {{-- เฉพาะวัน --}}
        <section class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">ว่างเพิ่มเฉพาะวัน</h2>
                <p class="mt-0.5 text-xs text-muted">สำหรับวันที่ว่างนอกเหนือจากเวลาประจำ</p>
            </div>
            @if ($this->dated->isNotEmpty())
                <ul class="divide-y divide-line text-sm">
                    @foreach ($this->dated as $w)
                        <li class="flex items-center justify-between gap-3 px-5 py-3" wire:key="dw-{{ $w->id }}">
                            <span class="text-ink">{{ $w->label() }}</span>
                            <button type="button" wire:click="removeWindow({{ $w->id }})" class="text-sm text-muted hover:text-red-600">ลบ</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form wire:submit="addDated" class="flex flex-wrap items-center gap-2 border-t border-line p-4 sm:p-5">
                <x-date-field model="dateOnly" placeholder="เลือกวันที่" :min="today()->toDateString()" />
                <select wire:model="dateStart" aria-label="เวลาเริ่ม" class="min-h-[44px] rounded-xl border-line bg-white/70 text-sm">
                    @foreach ($hours as $h) <option value="{{ $h }}">{{ $h }}</option> @endforeach
                </select>
                <span class="text-muted">ถึง</span>
                <select wire:model="dateEnd" aria-label="เวลาสิ้นสุด" class="min-h-[44px] rounded-xl border-line bg-white/70 text-sm">
                    @foreach ($hours as $h) <option value="{{ $h }}">{{ $h }}</option> @endforeach
                </select>
                <button type="submit" class="btn-primary px-4 py-2.5 text-sm">เพิ่ม</button>
            </form>
        </section>

        {{-- วันลา --}}
        <section class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">วันลา / ไม่รับงาน</h2>
                <p class="mt-0.5 text-xs text-muted">ลาทับงานที่มีคนจองไว้แล้วไม่ได้ ต้องส่งคำขอยกเลิกการรับงานจากหน้างานนั้นแทน</p>
            </div>
            @if ($this->timeOffs->isNotEmpty())
                <ul class="divide-y divide-line text-sm">
                    @foreach ($this->timeOffs as $off)
                        <li class="flex items-center justify-between gap-3 px-5 py-3" wire:key="off-{{ $off->id }}">
                            <span class="text-ink">
                                {{ $off->date->locale('th')->isoFormat('ddd D MMM') }}
                                · {{ $off->isWholeDay() ? 'ทั้งวัน' : substr($off->start_time, 0, 5).'–'.substr($off->end_time, 0, 5) }}
                                @if ($off->reason) <span class="text-muted">· {{ $off->reason }}</span> @endif
                            </span>
                            <button type="button" wire:click="removeTimeOff({{ $off->id }})" class="text-sm text-muted hover:text-red-600">ลบ</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form wire:submit="addTimeOff" class="space-y-3 border-t border-line p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <x-date-field model="offDate" placeholder="เลือกวันลา" :min="today()->toDateString()" />
                    <label class="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" wire:model.live="offWholeDay"> ทั้งวัน
                    </label>
                    @unless ($offWholeDay)
                        <select wire:model="offStart" aria-label="เวลาเริ่มลา" class="min-h-[44px] rounded-xl border-line bg-white/70 text-sm">
                            @foreach ($hours as $h) <option value="{{ $h }}">{{ $h }}</option> @endforeach
                        </select>
                        <span class="text-muted">ถึง</span>
                        <select wire:model="offEnd" aria-label="เวลาสิ้นสุดลา" class="min-h-[44px] rounded-xl border-line bg-white/70 text-sm">
                            @foreach ($hours as $h) <option value="{{ $h }}">{{ $h }}</option> @endforeach
                        </select>
                    @endunless
                </div>
                <div class="flex gap-2">
                    <input type="text" wire:model="offReason" maxlength="255" placeholder="เหตุผล (ไม่บังคับ)" class="min-h-[44px] min-w-0 flex-1 rounded-xl border-line bg-white/70 text-sm">
                    <button type="submit" class="btn-primary shrink-0 px-4 py-2.5 text-sm">บันทึก</button>
                </div>
            </form>
        </section>
    </div>
</div>
