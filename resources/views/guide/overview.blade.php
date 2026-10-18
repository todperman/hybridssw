{{-- อินโฟกราฟิกสรุปการใช้งาน อ่านจบใน 1 นาที ตัวเลขทุกตัวดึงจากการตั้งค่าจริงที่ GuideController ส่งมา
     ใช้ HTML/Tailwind + SVG ล้วน ไม่มีไลบรารีกราฟ เพื่อให้พิมพ์และเปิดบนมือถือได้ลื่น --}}
@php($maxHours = max(1, (int) ($branch?->max_booking_hours ?? 1)))
@php($window = (int) ($branch?->booking_window_days ?? 30))
@php($maxPeople = max(1, (int) ($branch?->max_trainees ?? 5)))
@php($journey = $showMember ? [
    ['user-round-plus', 'สมัคร', $approvalNeeded ? 'กรอกข้อมูล รอเจ้าหน้าที่อนุมัติ' : 'กรอกข้อมูล ใช้งานได้ทันที'],
    ['calendar-days', 'ดูปฏิทิน', 'เห็นวัน/ชั่วโมงที่ว่าง และ Trainer ที่ว่าง'],
    ['users', 'เลือกคนและ Trainer', 'สูงสุด '.$maxPeople.' คน ราคาคิดทั้งกลุ่ม'],
    ['timer', 'ชำระใน '.$holdMinutes.' นาที', 'ระบบกันเวลาไว้ให้ระหว่างจ่าย'],
    ['badge-check', 'ยืนยันการจอง', 'แจ้งทางกระดิ่งและอีเมลทันที'],
    ['dumbbell', 'เข้าใช้ยิม', 'เตือนก่อน '.$reminderLead.' นาที แจ้งเลขอ้างอิงที่เคาน์เตอร์'],
] : [
    ['badge-check', 'สมัครและอนุมัติ', 'ภายในเริ่มทันที ภายนอกรอตรวจใบรับรอง'],
    ['clock', 'ตั้งเวลาว่าง', 'ประจำสัปดาห์ + เฉพาะวัน + วันลา'],
    ['qr-code', 'ชวนลูกทีม', 'ลิงก์หรือ QR ลูกทีมสมัครเองเข้าทีมทันที'],
    ['calendar-plus', 'จองให้ลูกเทรน', 'เลือกลูกทีม/กลุ่ม แล้วเลือกผู้ชำระ'],
    ['timer', 'ลูกเทรนชำระใน '.$holdMinutes.' นาที', 'ระบบแจ้งลูกเทรนให้จ่ายเอง'],
    ['clipboard-list', 'ดูงานของคุณ', 'ขอยกเลิกรับงานได้พร้อมเหตุผล'],
])
@php($maxPrice = collect($priceRows)->max('with') ?: 1)
@php($paid = $example ? $example['total'] - $example['share'] : 0)

{{-- 1. เส้นทางการใช้งาน --}}
<div class="card overflow-hidden">
    <div class="card-head flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-[17px] font-bold text-grad">{{ $showMember ? 'เส้นทางการจองใน 6 ขั้น' : 'เทรนเนอร์เริ่มงานใน 6 ขั้น' }}</h2>
            <p class="mt-0.5 text-xs text-muted">อ่านจบใน 1 นาที</p>
        </div>
        <span class="chip bg-brand-light/30 text-brand-dark">ภาพรวม</span>
    </div>
    <div class="p-5">
        <ol class="relative grid gap-5 sm:grid-cols-3 lg:grid-cols-6 lg:gap-3">
            {{-- เส้นเชื่อมแนวนอนบนจอกว้าง --}}
            <div class="pointer-events-none absolute left-[8%] right-[8%] top-7 hidden h-0.5 bg-gradient-to-r from-brand-light via-brand to-accent lg:block" aria-hidden="true"></div>
            @foreach ($journey as $i => [$icon, $title, $desc])
                <li class="relative flex gap-3 lg:flex-col lg:items-center lg:text-center">
                    <div class="relative z-10 grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-dark to-brand text-white shadow-soft ring-4 ring-paper">
                        <x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" stroke-width="1.9" />
                        <span class="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full bg-accent font-display text-[12px] font-bold text-accent-ink">{{ $i + 1 }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="font-display text-[15px] font-bold leading-tight text-ink">{{ $title }}</p>
                        <p class="mt-1 text-[12.5px] leading-snug text-muted">{{ $desc }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    {{-- 2. นาฬิการอชำระ --}}
    <div class="card overflow-hidden">
        <div class="card-head">
            <h2 class="font-display text-[17px] font-bold text-grad">หลังกดจอง มีเวลาจ่าย {{ $holdMinutes }} นาที</h2>
            <p class="mt-0.5 text-xs text-muted">ระหว่างนี้ไม่มีใครจองทับได้</p>
        </div>
        <div class="flex items-center gap-5 p-5">
            @php($ratio = min(1, $holdMinutes / 60))
            @php($circ = 2 * M_PI * 44)
            <svg viewBox="0 0 110 110" class="h-32 w-32 shrink-0" role="img" aria-label="เวลารอชำระ {{ $holdMinutes }} นาที">
                <circle cx="55" cy="55" r="44" fill="none" stroke="#e8eef1" stroke-width="12" />
                <circle cx="55" cy="55" r="44" fill="none" stroke="url(#holdGrad)" stroke-width="12" stroke-linecap="round"
                        stroke-dasharray="{{ $circ * $ratio }} {{ $circ }}" transform="rotate(-90 55 55)" />
                <defs><linearGradient id="holdGrad" x1="0" x2="1"><stop offset="0" stop-color="#2bbbd7"/><stop offset="1" stop-color="#007595"/></linearGradient></defs>
                <text x="55" y="52" text-anchor="middle" class="font-display" font-size="24" font-weight="800" fill="#00334a">{{ $holdMinutes }}</text>
                <text x="55" y="68" text-anchor="middle" font-size="10" fill="#5b676c">นาที</text>
            </svg>
            <ul class="space-y-2.5 text-[13.5px] leading-snug">
                <li class="flex items-start gap-2"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span><span><strong class="text-ink">จ่ายทัน</strong> → ยืนยันการจองทันที ได้อีเมล+กระดิ่ง</span></li>
                <li class="flex items-start gap-2"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-amber-400"></span><span><strong class="text-ink">ใกล้เวลาเริ่ม</strong> → หมดเวลาเมื่อถึงเวลาเริ่ม แม้ยังไม่ครบ {{ $holdMinutes }} นาที</span></li>
                <li class="flex items-start gap-2"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-line"></span><span><strong class="text-ink">ไม่จ่าย</strong> → รายการหมดอายุ คืนช่วงเวลาให้คนอื่น ต้องจองใหม่</span></li>
            </ul>
        </div>
    </div>

    {{-- 3. เส้นเวลาการเลื่อน --}}
    <div class="card overflow-hidden">
        <div class="card-head">
            <h2 class="font-display text-[17px] font-bold text-grad">เลื่อนวันเวลาได้ถึงเมื่อไร</h2>
            <p class="mt-0.5 text-xs text-muted">เส้นตายคือเที่ยงคืนก่อนวันใช้งาน</p>
        </div>
        <div class="p-5">
            <div class="relative mt-6 h-3 rounded-full bg-mist">
                <div class="absolute inset-y-0 left-0 w-[62%] rounded-l-full bg-gradient-to-r from-emerald-400 to-emerald-500"></div>
                <div class="absolute inset-y-0 left-[62%] right-0 rounded-r-full bg-gradient-to-r from-amber-300 to-amber-400"></div>
                <span class="absolute -top-6 left-0 text-[11px] font-medium text-muted">วันจอง</span>
                <span class="absolute -top-6 left-[62%] -translate-x-1/2 whitespace-nowrap rounded-full bg-ink px-2 py-0.5 text-[11px] font-semibold text-white">00:00 วันใช้งาน</span>
                <span class="absolute -top-6 right-0 text-[11px] font-medium text-muted">เวลาเริ่ม</span>
                <span class="absolute left-[62%] top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-4 border-white bg-ink shadow-soft"></span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-[13px] leading-snug">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="font-semibold text-emerald-800">เลื่อนเองได้ทันที</p>
                    <p class="mt-0.5 text-emerald-900/80">ราคาเท่าเดิม Trainer และผู้เข้าร่วมเดิม</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3">
                    <p class="font-semibold text-amber-800">ส่งคำขอพร้อมเหตุผล</p>
                    <p class="mt-0.5 text-amber-900/80">เจ้าหน้าที่พิจารณา แจ้งผลทางกระดิ่ง/อีเมล</p>
                </div>
            </div>
            <p class="mt-3 text-[12.5px] text-muted">ตัวอย่าง: จองวันที่ 10 เวลา 18:00 เลื่อนเองได้ถึง 9 เวลา 23:59 · การยกเลิกทำโดยเจ้าหน้าที่และคืนเงินเต็มจำนวน</p>
        </div>
    </div>
</div>

@if ($priceRows !== [])
{{-- 4. กราฟราคา --}}
<div class="card overflow-hidden">
    <div class="card-head flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-display text-[17px] font-bold text-grad">ราคาเป็นของกลุ่ม ไม่ใช่ต่อคน</h2>
            <p class="mt-0.5 text-xs text-muted">บาทต่อชั่วโมงทั้งกลุ่ม · ยิ่งมากัน ยิ่งถูกต่อคน</p>
        </div>
        <div class="flex gap-3 text-[12px]">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-brand-dark"></span>มี Trainer</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-brand-light/70"></span>ไม่มี Trainer</span>
        </div>
    </div>
    <div class="p-5">
        <div class="grid items-end gap-2" style="grid-template-columns: repeat({{ count($priceRows) }}, minmax(0, 1fr));">
            @foreach ($priceRows as $row)
                <div class="flex flex-col items-center gap-1.5">
                    <div class="flex h-40 w-full items-end justify-center gap-1 sm:gap-1.5">
                        <div class="group relative flex w-1/2 max-w-[28px] flex-col items-center justify-end">
                            <span class="mb-1 font-display text-[10px] font-bold leading-none text-brand-dark sm:text-[11px]">{{ number_format($row['with']) }}</span>
                            <div class="w-full rounded-t-md bg-brand-dark" style="height: {{ max(6, round($row['with'] / $maxPrice * 120)) }}px"></div>
                        </div>
                        <div class="relative flex w-1/2 max-w-[28px] flex-col items-center justify-end">
                            <span class="mb-1 font-display text-[10px] font-bold leading-none text-brand-deep/80 sm:text-[11px]">{{ number_format($row['without']) }}</span>
                            <div class="w-full rounded-t-md bg-brand-light/70" style="height: {{ max(6, round($row['without'] / $maxPrice * 120)) }}px"></div>
                        </div>
                    </div>
                    <p class="font-display text-[13px] font-bold text-ink">{{ $row['people'] }} คน</p>
                    <p class="text-[11px] leading-none text-muted">฿{{ number_format($row['with'] / $row['people']) }}/คน</p>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-[12.5px] text-muted">ตัวเลขใต้แท่ง = ราคาต่อคนต่อชั่วโมงแบบมี Trainer · จองหลายชั่วโมงคูณตามจำนวนชั่วโมง · กด "ดูราคา" ด้านบนเพื่อดูตารางเต็ม</p>
    </div>
</div>
@endif

<div class="grid gap-4 lg:grid-cols-2">
    {{-- 5. สิทธิ์ใช้ฟรี --}}
    @if ($showMember && $example)
    <div class="card overflow-hidden">
        <div class="card-head">
            <h2 class="font-display text-[17px] font-bold text-grad">บุคลากร รพ. / แพทย์ ส่วนของตัวเองฟรี</h2>
            <p class="mt-0.5 text-xs text-muted">รวมค่า Trainer · เพื่อนร่วมรอบจ่ายส่วนของตัวเองตามปกติ</p>
        </div>
        <div class="p-5">
            <div class="flex items-end justify-center gap-4">
                @for ($i = 0; $i < $example['people']; $i++)
                    @php($free = $i === 0)
                    <div class="flex flex-col items-center gap-1.5">
                        <div class="relative grid h-16 w-16 place-items-center rounded-full {{ $free ? 'bg-emerald-500 text-white ring-4 ring-emerald-200' : 'bg-mist text-muted' }}">
                            @svg('lucide-user-round', 'h-8 w-8', ['stroke-width' => '1.8'])
                            @if ($free)
                                <span class="absolute -bottom-2 rounded-full bg-emerald-700 px-2 py-0.5 text-[10px] font-bold text-white">ฟรี</span>
                            @endif
                        </div>
                        <span class="mt-1 font-display text-[13px] font-bold {{ $free ? 'text-emerald-700' : 'text-ink' }}">{{ $free ? '฿0' : '฿'.number_format($example['share']) }}</span>
                    </div>
                @endfor
            </div>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2 rounded-2xl bg-mist/70 px-3 py-3 text-center font-display text-[14px] font-bold text-ink">
                <span>฿{{ number_format($example['total']) }}</span>
                <span class="text-muted">−</span>
                <span class="text-emerald-700">฿{{ number_format($example['share']) }}</span>
                <span class="text-muted">=</span>
                <span class="rounded-full bg-brand-dark px-3 py-1 text-white">จ่าย ฿{{ number_format($paid) }}</span>
            </div>
            <p class="mt-2 text-center text-[12px] text-muted">ตัวอย่าง {{ $example['people'] }} คน มี Trainer 1 ชม. มี 1 คนที่มีสิทธิ์ · ทุกคนมีสิทธิ์ = ฿0 ยืนยันทันที</p>
            @if ($memberTypes->isNotEmpty())
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    @foreach ($memberTypes as $type)
                        <span class="chip border border-emerald-200 bg-emerald-50 text-emerald-800">{{ $type->name }} · {{ $type->quotaLabel() }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- 6. สถานะการจอง --}}
    <div class="card overflow-hidden">
        <div class="card-head">
            <h2 class="font-display text-[17px] font-bold text-grad">สถานะการจองเปลี่ยนยังไง</h2>
            <p class="mt-0.5 text-xs text-muted">ดูได้ที่ "{{ $showMember ? 'การจองของฉัน' : 'งาน' }}" และกระดิ่ง</p>
        </div>
        <div class="p-5">
            <div class="flex flex-col items-stretch gap-3">
                <div class="flex items-center gap-2">
                    <span class="flex-1 rounded-2xl border border-amber-300 bg-amber-50 px-3 py-2.5 text-center">
                        <span class="block font-display text-[14px] font-bold text-amber-800">รอชำระเงิน</span>
                        <span class="text-[11px] text-amber-900/70">กันเวลาไว้ {{ $holdMinutes }} นาที</span>
                    </span>
                    @svg('lucide-arrow-right', 'h-5 w-5 shrink-0 text-muted', ['stroke-width' => '2'])
                    <span class="flex-1 rounded-2xl border border-emerald-300 bg-emerald-50 px-3 py-2.5 text-center">
                        <span class="block font-display text-[14px] font-bold text-emerald-800">ยืนยันการจอง</span>
                        <span class="text-[11px] text-emerald-900/70">ชำระแล้ว / ใช้สิทธิ์ฟรี</span>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[12px]">
                    <div class="rounded-2xl border border-line bg-white/70 px-3 py-2.5">
                        <p class="font-semibold text-muted">↓ ไม่จ่ายในเวลา</p>
                        <p class="mt-0.5 font-display text-[13px] font-bold text-ink">หมดอายุ</p>
                        <p class="text-muted">ปล่อยเวลาให้คนอื่น จองใหม่ได้</p>
                    </div>
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2.5">
                        <p class="font-semibold text-red-700/80">↓ เจ้าหน้าที่ยกเลิก</p>
                        <p class="mt-0.5 font-display text-[13px] font-bold text-red-800">ยกเลิก + คืนเงิน</p>
                        <p class="text-red-900/70">เต็มจำนวน ไปช่องทางเดิม</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 7. ช่องทางชำระ --}}
<div class="card overflow-hidden">
    <div class="card-head">
        <h2 class="font-display text-[17px] font-bold text-grad">จ่ายได้ทางไหน ยืนยันเร็วแค่ไหน</h2>
        <p class="mt-0.5 text-xs text-muted">{{ $omise ? 'ชำระออนไลน์ ระบบยืนยันให้เอง ไม่ต้องส่งสลิป' : 'โอนแล้วแจ้งเจ้าหน้าที่พร้อมเลขอ้างอิง' }}</p>
    </div>
    <div class="p-5">
        @if ($omise && $methods->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-{{ min(5, max(2, $methods->count())) }}">
                @foreach ($methods as $key => $m)
                    <div class="flex flex-col items-center rounded-2xl border border-line bg-white/70 p-4 text-center">
                        <div class="flex -space-x-2">
                            @foreach (array_slice($m['logos'], 0, 3) as $logo)
                                <img src="{{ \App\Services\Payments\Omise\PaymentMethods::logoUrl($logo) }}" alt="" class="h-10 w-10 rounded-full border-2 border-white bg-white object-contain p-1 shadow-soft">
                            @endforeach
                        </div>
                        <p class="mt-2 font-display text-[14px] font-bold text-ink">{{ $m['label'] }}</p>
                        <p class="text-[12px] text-muted">{{ $m['hint'] }}</p>
                        <span class="mt-2 chip {{ in_array($key, ['promptpay', 'mobile_banking']) ? 'bg-emerald-100 text-emerald-800' : 'bg-brand-light/30 text-brand-dark' }}">
                            {{ match ($key) { 'promptpay' => 'ยืนยันในไม่กี่วินาที', 'card' => 'มี OTP ธนาคาร', 'mobile_banking' => 'ยืนยันในแอปธนาคาร', 'rabbit_linepay' => 'ยืนยันใน LINE 20 นาที', 'truemoney' => 'OTP ในแอป', default => 'ออนไลน์' } }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <div class="flex items-center gap-3 rounded-2xl border border-line bg-white/70 px-4 py-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-light/30 text-brand-dark">@svg('lucide-banknote', 'h-5 w-5')</span>
                    <div><p class="font-display text-[14px] font-bold text-ink">โอนเงิน</p><p class="text-[12px] text-muted">ตามยอดและบัญชีที่แสดงในหน้าการจอง</p></div>
                </div>
                @svg('lucide-arrow-right', 'h-5 w-5 rotate-90 text-muted sm:rotate-0', ['stroke-width' => '2'])
                <div class="flex items-center gap-3 rounded-2xl border border-line bg-white/70 px-4 py-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-700">@svg('lucide-receipt', 'h-5 w-5')</span>
                    <div><p class="font-display text-[14px] font-bold text-ink">เจ้าหน้าที่บันทึกรับชำระ</p><p class="text-[12px] text-muted">สถานะเปลี่ยนเป็นยืนยัน แจ้งทุกคนในรอบ</p></div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- 8. ใครทำอะไรได้ --}}
<div class="card overflow-hidden">
    <div class="card-head">
        <h2 class="font-display text-[17px] font-bold text-grad">ใครทำอะไรได้บ้าง</h2>
    </div>
    <div class="grid gap-px bg-line sm:grid-cols-3">
        @foreach ([
            ['user-round', 'สมาชิก', 'bg-brand-light/20 text-brand-dark', ['ดูปฏิทินและจองยิมเอง', 'เพิ่มเพื่อนร่วมรอบ เลือก Trainer', 'จ่ายออนไลน์ เลื่อนเอง/ขอเลื่อน', 'ใช้โค้ดส่วนลดและสิทธิ์ฟรี']],
            ['dumbbell', 'เทรนเนอร์', 'bg-accent/40 text-accent-ink', ['ตั้งเวลาว่าง วันลา', 'ชวนลูกทีมด้วยลิงก์/QR สร้างกลุ่ม', 'จองยิมให้ลูกเทรน (ลูกเทรนจ่าย)', 'ขอยกเลิกการรับงานพร้อมเหตุผล']],
            ['shield-check', 'เจ้าหน้าที่', 'bg-mist text-ink', ['อนุมัติสมาชิก/เทรนเนอร์', 'กำหนดสิทธิ์ฟรีและโค้ดส่วนลด', 'อนุมัติคำขอเลื่อน เปลี่ยน Trainer', 'ยกเลิกและคืนเงิน บันทึกรับชำระ']],
        ] as [$icon, $role, $tone, $items])
            <div class="bg-white/80 p-5">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl {{ $tone }}"><x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" stroke-width="1.9" /></span>
                    <p class="font-display text-[16px] font-bold text-ink">{{ $role }}</p>
                </div>
                <ul class="mt-3 space-y-1.5 text-[13px] text-muted">
                    @foreach ($items as $item)
                        <li class="flex items-start gap-2">@svg('lucide-check', 'mt-0.5 h-4 w-4 shrink-0 text-emerald-600', ['stroke-width' => '2.5']) {{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>

<p class="text-center text-[12.5px] text-muted">อยากรู้รายละเอียดแต่ละขั้น เลือกบทด้านบน หรือกด "ดาวน์โหลด PDF" เพื่อเก็บทั้งคู่มือไว้อ่านหรือพิมพ์</p>
