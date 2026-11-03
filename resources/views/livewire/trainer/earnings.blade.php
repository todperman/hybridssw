<div class="pb-16">
    @php($trainer = $this->trainer())
    @php($external = $trainer->type === \App\Enums\TrainerType::External)
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
                <p class="font-display text-lg font-bold text-ink">บัญชีเทรนเนอร์ภายในไม่มีส่วนแบ่งรายงาน</p>
                <p class="mt-1 text-sm text-muted">ส่วนแบ่งต่อการจองมีเฉพาะเทรนเนอร์ภายนอก ตามนโยบายของยิม</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
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
                    <p class="px-5 pb-4 text-[12px] leading-relaxed text-muted">ส่วนแบ่งคิดเป็นค่า Trainer ต่อชั่วโมงตามจำนวนคน × ชั่วโมง นับเป็นรายได้เมื่อหมดเวลาใช้งาน ส่วนลดหรือสิทธิ์ฟรีของลูกค้าไม่ลดส่วนแบ่งของคุณ</p>
                </section>

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
        @endunless
    </div>
</div>
