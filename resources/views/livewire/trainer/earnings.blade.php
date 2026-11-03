<div class="pb-16">
    @php($trainer = $this->trainer())
    @php($external = $this->eligible())
    @php($shareTable = $this->shareTable())
    @php($s = $this->summary)
    @php($monthStart = $this->monthStart)
    @php($byDay = $this->rows->groupBy(fn ($e) => $e->earned_at->toDateString()))
    @php($maxBar = max(1, collect($this->history)->max('amount')))

    <x-page-hero eyebrow="รายได้" title="ส่วนแบ่งของคุณ" :subtitle="$trainer->branch?->name" pattern="wallball" pattern-alt="rower">
        <x-slot:stats>
            @if ($external)
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">{{ $monthStart->locale('th')->isoFormat('MMM YYYY') }}</p>
                        <p class="font-display text-2xl font-extrabold text-white">฿{{ number_format($s['amount']) }}</p>
                        <p class="text-[12px] text-white/70">{{ $s['jobs'] }} งาน · {{ $s['hours'] }} ชม.</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">รอจ่ายทั้งหมด</p>
                        <p class="font-display text-2xl font-extrabold text-accent">฿{{ number_format($this->outstanding) }}</p>
                        <p class="text-[12px] text-white/70">จ่ายเป็นรอบรายเดือน</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">จ่ายแล้วเดือนนี้</p>
                        <p class="font-display text-2xl font-extrabold text-white">฿{{ number_format($s['paid']) }}</p>
                        <p class="text-[12px] text-white/70">{{ $this->lastPaid ? 'ล่าสุด '.$this->lastPaid->paid_at->locale('th')->isoFormat('D MMM') : 'ยังไม่มีรอบจ่าย' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                        <p class="text-[11px] uppercase tracking-wide text-white/70">รอถึงเวลาใช้งาน</p>
                        <p class="font-display text-2xl font-extrabold text-white">฿{{ number_format($s['accrued']) }}</p>
                        <p class="text-[12px] text-white/70">งานที่ยืนยันแล้วแต่ยังไม่ถึงวัน</p>
                    </div>
                </div>
            @endif
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
        @unless ($external)
            <div class="card p-6 text-center">
                <p class="font-display text-lg font-bold text-ink">บัญชีเทรนเนอร์ภายในไม่มีส่วนแบ่งต่อรอบ</p>
                <p class="mt-1 text-sm text-muted">ส่วนแบ่งต่อการจองมีเฉพาะเทรนเนอร์ภายนอก ตามนโยบายของยิม</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
              {{-- คอลัมน์ซ้าย: กราฟ บัญชี ตาราง --}}
              <div class="space-y-4">
                {{-- กราฟ 6 เดือน --}}
                <section class="card overflow-hidden self-start">
                    <div class="card-head flex items-center justify-between">
                        <h2 class="font-display text-[17px] font-bold text-grad">6 เดือนล่าสุด</h2>
                        <div class="flex gap-3 text-[11px] text-muted">
                            <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-sm bg-brand-dark"></i>จ่ายแล้ว</span>
                            <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-sm bg-brand-light/70"></i>รอจ่าย/รอถึงเวลา</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-6 items-end gap-2 p-5">
                        @foreach ($this->history as $h)
                            @php($hPaid = round($h['paid'] / $maxBar * 110))
                            @php($hRest = round(($h['amount'] - $h['paid']) / $maxBar * 110))
                            <button type="button" wire:click="$set('month', '{{ $h['month']->format('Y-m') }}')" class="flex flex-col items-center gap-1.5 {{ $h['month']->isSameMonth($monthStart) ? '' : 'opacity-70 hover:opacity-100' }}">
                                <span class="font-display text-[11px] font-bold text-ink">{{ $h['amount'] > 0 ? number_format($h['amount'] / 1000, 1).'k' : '–' }}</span>
                                <span class="flex h-[110px] w-7 flex-col justify-end overflow-hidden rounded-t-md bg-mist">
                                    <span class="w-full bg-brand-light/70" style="height: {{ $hRest }}px"></span>
                                    <span class="w-full bg-brand-dark" style="height: {{ $hPaid }}px"></span>
                                </span>
                                <span class="text-[11px] {{ $h['month']->isSameMonth($monthStart) ? 'font-bold text-brand-deep' : 'text-muted' }}">{{ $h['month']->locale('th')->isoFormat('MMM') }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- บัญชีรับเงิน: เทรนเนอร์กรอกเอง แอดมินเห็นตอนโอน --}}
                <section class="card overflow-hidden self-start">
                    <div class="card-head flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-[17px] font-bold text-grad">บัญชีรับเงิน</h2>
                            <p class="mt-0.5 text-xs text-muted">ยิมโอนส่วนแบ่งเข้าบัญชีนี้</p>
                        </div>
                        @unless ($editingBank)
                            <button type="button" wire:click="openBank" class="btn-ghost px-4 py-2 text-sm">{{ $trainer->hasBankAccount() ? 'แก้ไข' : 'กรอกบัญชี' }}</button>
                        @endunless
                    </div>

                    @if ($editingBank)
                        <form wire:submit="saveBank" class="space-y-4 p-5">
                            <div class="field">
                                <select wire:model="bank_code" id="bank_code" class="peer field-input appearance-none @error('bank_code') border-red-400 @enderror">
                                    <option value="">เลือกธนาคาร</option>
                                    @foreach (\App\Enums\ThaiBank::options() as $code => $name)
                                        <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                                <label for="bank_code" class="field-label !top-3.5 !text-[11px] !font-medium">ธนาคาร</label>
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-muted">@svg('lucide-chevron-down', 'h-4 w-4', ['stroke-width' => '2'])</span>
                                @error('bank_code') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <input wire:model.blur="bank_account_number" id="bank_account_number" type="text" inputmode="numeric" placeholder=" " class="peer field-input @error('bank_account_number') border-red-400 @enderror">
                                <label for="bank_account_number" class="field-label">เลขบัญชี (ตัวเลข 10 หลัก)</label>
                                @error('bank_account_number') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <input wire:model.blur="bank_account_name" id="bank_account_name" type="text" placeholder=" " class="peer field-input @error('bank_account_name') border-red-400 @enderror">
                                <label for="bank_account_name" class="field-label">ชื่อบัญชี (ตามหน้าสมุด)</label>
                                @error('bank_account_name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <input wire:model.blur="promptpay_id" id="promptpay_id" type="text" inputmode="numeric" placeholder=" " class="peer field-input @error('promptpay_id') border-red-400 @enderror">
                                <label for="promptpay_id" class="field-label">พร้อมเพย์ (ไม่บังคับ: เบอร์โทรหรือเลขบัตรประชาชน)</label>
                                @error('promptpay_id') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <x-guide.tip tone="warn">ชื่อบัญชีต้องตรงกับชื่อของคุณ ทุกครั้งที่แก้ระบบจะแจ้งแอดมินให้ตรวจก่อนโอน</x-guide.tip>
                            <div class="flex gap-2">
                                <button type="button" wire:click="$set('editingBank', false)" class="btn-ghost">ยกเลิก</button>
                                <button type="submit" wire:loading.attr="disabled" class="btn-grad flex-1">บันทึก</button>
                            </div>
                        </form>
                    @elseif ($trainer->hasBankAccount())
                        <div class="flex items-center gap-4 p-5">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl font-display text-[11px] font-extrabold text-white" style="background: {{ $trainer->bank()->color() }}">{{ $trainer->bank()->value }}</span>
                            <div class="min-w-0">
                                <p class="font-display text-[16px] font-bold tracking-wide text-ink">{{ \App\Enums\ThaiBank::formatAccount($trainer->bank_account_number) }}</p>
                                <p class="text-[13px] text-muted">{{ $trainer->bank()->label() }} · {{ $trainer->bank_account_name }}</p>
                                @if ($trainer->promptpay_id)<p class="text-[12px] text-muted">พร้อมเพย์ {{ $trainer->promptpay_id }}</p>@endif
                            </div>
                        </div>
                    @else
                        <div class="p-5 text-center">
                            <p class="text-sm text-muted">ยังไม่ได้กรอกบัญชี ยิมจะโอนส่วนแบ่งให้ไม่ได้จนกว่าจะกรอก</p>
                            <button type="button" wire:click="openBank" class="btn-primary mt-3 px-5">กรอกบัญชีรับเงิน</button>
                        </div>
                    @endif
                </section>

                {{-- ตารางส่วนแบ่ง: ดึงจากการตั้งค่าของสาขา แอดมินปิดการแสดงได้ --}}
                @if ($shareTable !== [])
                <section class="card overflow-hidden self-start">
                    <div class="card-head">
                        <h2 class="font-display text-[17px] font-bold text-grad">ตารางส่วนแบ่งของคุณ</h2>
                        <p class="mt-0.5 text-xs text-muted">ค่า Trainer ต่อชั่วโมง ตามจำนวนคนในรอบ × จำนวนชั่วโมงที่จอง</p>
                    </div>
                    <table class="w-full text-[13.5px]">
                        <thead class="bg-mist/60 text-[11px] font-semibold text-muted">
                            <tr><th class="px-5 py-2 text-start">จำนวนคน</th><th class="px-3 py-2 text-end">คุณได้ / ชม.</th><th class="px-5 py-2 text-end text-muted/70">ลูกค้าจ่าย / ชม.</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($shareTable as $row)
                                <tr>
                                    <td class="px-5 py-2 font-display font-bold text-ink">{{ $row['people'] }} คน</td>
                                    <td class="px-3 py-2 text-end font-display text-[15px] font-bold text-brand-deep">฿{{ number_format($row['trainer']) }}</td>
                                    <td class="px-5 py-2 text-end text-muted">฿{{ number_format($row['price']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <ul class="space-y-1 px-5 py-4 text-[12.5px] leading-relaxed text-muted">
                        @foreach ($this->policyLines() as $line)
                            <li class="flex items-start gap-2">@svg('lucide-check', 'mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600', ['stroke-width' => '2.5']) {{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
                @endif

              </div>

              {{-- คอลัมน์ขวา: รายวัน --}}
              <div>
                {{-- รายวัน --}}
                <section class="card overflow-hidden self-start">
                    <div class="card-head flex items-center justify-between gap-2">
                        <button type="button" wire:click="shiftMonth(-1)" class="grid h-9 w-9 place-items-center rounded-full text-ink hover:bg-white/70" aria-label="เดือนก่อนหน้า">@svg('lucide-chevron-left', 'h-5 w-5', ['stroke-width' => '2.2'])</button>
                        <div class="text-center">
                            <h2 class="font-display text-[17px] font-bold text-grad">{{ $monthStart->locale('th')->isoFormat('MMMM YYYY') }}</h2>
                            <p class="text-xs text-muted">{{ $s['jobs'] }} งาน · {{ $s['hours'] }} ชม. · ฿{{ number_format($s['amount']) }}</p>
                        </div>
                        <button type="button" wire:click="shiftMonth(1)" class="grid h-9 w-9 place-items-center rounded-full text-ink hover:bg-white/70" aria-label="เดือนถัดไป">@svg('lucide-chevron-right', 'h-5 w-5', ['stroke-width' => '2.2'])</button>
                    </div>

                    @if ($byDay->isEmpty())
                        <p class="px-5 py-10 text-center text-sm text-muted">เดือนนี้ยังไม่มีงานที่นับส่วนแบ่ง</p>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($byDay as $ymd => $items)
                                <li class="px-4 py-3 sm:px-5">
                                    <div class="flex items-center justify-between">
                                        <p class="font-display text-[14px] font-bold text-ink">{{ \Carbon\CarbonImmutable::parse($ymd)->locale('th')->isoFormat('ddd D MMM') }}</p>
                                        <p class="font-display text-[14px] font-bold text-brand-deep">฿{{ number_format($items->sum('amount')) }}</p>
                                    </div>
                                    <ul class="mt-1.5 space-y-1.5">
                                        @foreach ($items as $e)
                                            <li>
                                                <a href="{{ route('reservations.show', $e->reservation->reference) }}" wire:navigate class="flex items-center justify-between gap-3 rounded-xl border border-line bg-white/70 px-3 py-2 text-[13px] transition hover:bg-white">
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-ink">{{ $e->reservation->timeLabel() }} · {{ $e->reservation->payer?->user?->displayName() }}</span>
                                                        <span class="block text-[12px] text-muted">{{ $e->people }} คน × ฿{{ number_format((float) $e->per_hour) }}/ชม. × {{ $e->hours }} ชม.</span>
                                                    </span>
                                                    <span class="flex shrink-0 flex-col items-end gap-0.5">
                                                        <span class="font-display font-bold text-ink">฿{{ number_format((float) $e->amount) }}</span>
                                                        <span class="chip {{ match ($e->status) { \App\Enums\EarningStatus::Paid => 'bg-emerald-100 text-emerald-800', \App\Enums\EarningStatus::Payable => 'bg-amber-100 text-amber-800', default => 'bg-mist text-muted' } }}">{{ $e->status->label() }}</span>
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
              </div>
            </div>
        @endunless
    </div>
</div>
