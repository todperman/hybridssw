<div class="pb-16">
    @php($member = $this->member())
    @php($approval = \App\Support\BookingRules::needsApproval(selfBooked: true))
    @php($credits = \App\Support\BookingRules::creditsRequired())
    @php($selected = \Carbon\CarbonImmutable::parse($day))
    @php($suspended = $member->isSuspended())

    <x-page-hero eyebrow="จองรอบ" title="เลือกรอบที่สะดวก" pattern="rower" pattern-alt="kettlebell"
                 :subtitle="$member->branch->name">
        <x-slot:action>
            <a href="{{ route('member.bookings') }}" wire:navigate
               class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                @svg('lucide-ticket', 'h-4 w-4', ['stroke-width' => '1.9'])
                คิวของฉัน
            </a>
        </x-slot:action>

        <x-slot:stats>
            <p class="mt-4 flex max-w-md items-start gap-2 text-[13px] leading-relaxed text-white/80">
                @svg($approval ? 'lucide-hourglass' : 'lucide-circle-check', 'mt-0.5 h-4 w-4 shrink-0 text-brand-light', ['stroke-width' => '2'])
                @if ($approval)
                    กดจองแล้วระบบจะส่งคำขอให้แอดมินยืนยัน ที่นั่งถูกกันไว้ให้คุณระหว่างรอ
                @else
                    กดจองแล้วได้ที่นั่งทันที
                @endif
            </p>

            @if ($suspended)
                <p class="mt-3 max-w-md rounded-xl border border-red-300/40 bg-red-500/15 px-4 py-2.5 text-[13px] text-red-100">
                    ถูกระงับสิทธิ์จองถึง {{ $member->suspended_until?->format('d/m/Y') }}
                </p>
            @endif
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-6 sm:px-6">

        {{-- แถบเลือกวัน เลื่อนแนวนอนได้บนมือถือ จุดใต้วันที่ = วันนั้นยังมีรอบว่าง --}}
        <div class="card p-2">
            <div class="scroll-quiet -mx-0.5 flex snap-x gap-1.5 overflow-x-auto px-0.5 pb-0.5" role="tablist" aria-label="เลือกวัน">
                @foreach ($this->days as $d)
                    @php($ymd = $d->toDateString())
                    @php($active = $ymd === $day)
                    @php($open = (int) $this->openCountByDay->get($ymd, 0))

                    <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
                            wire:click="selectDay('{{ $ymd }}')" wire:key="day-{{ $ymd }}"
                            class="flex min-w-[3.6rem] shrink-0 snap-start flex-col items-center rounded-2xl px-2 py-2.5 transition
                                   {{ $active ? 'bg-brand-dark text-white shadow-soft' : 'text-ink hover:bg-mist' }}">
                        <span class="text-[11px] leading-none {{ $active ? 'text-white/70' : 'text-muted' }}">
                            {{ $d->isToday() ? 'วันนี้' : $d->locale('th')->isoFormat('dd') }}
                        </span>
                        <span class="mt-1 font-display text-lg font-bold leading-none">{{ $d->format('j') }}</span>
                        <span class="mt-1.5 h-1.5 w-1.5 rounded-full
                                     {{ $open > 0 ? ($active ? 'bg-brand-light' : 'bg-brand') : 'bg-transparent' }}"></span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- รอบของวันที่เลือก --}}
        <div class="card overflow-hidden">
            <div class="card-head flex items-center justify-between gap-3">
                <h2 class="font-display text-[17px] font-bold text-grad">
                    {{ $selected->locale('th')->isoFormat('dddd D MMMM') }}
                </h2>

                @if ($this->sessions->isNotEmpty())
                    <span class="chip bg-brand-light/40 text-brand-dark">{{ $this->sessions->count() }} รอบ</span>
                @endif
            </div>

            @if ($this->sessions->isEmpty())
                <div class="px-5 py-12 text-center sm:py-14">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="stopwatch" class="h-7 w-7 text-brand-deep" />
                    </span>
                    <p class="mt-4 font-display text-lg font-bold text-ink">ไม่มีรอบที่จองได้</p>
                    <p class="mt-1 text-sm text-muted">ลองเลือกวันอื่นจากแถบด้านบน</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->sessions as $session)
                        @php($mine = $this->mine->get($session->id))
                        @php($left = $session->seatsRemaining())

                        <li wire:key="s-{{ $session->id }}" class="flex items-center gap-3 px-4 py-4 sm:gap-5 sm:px-5">
                            <div class="w-14 shrink-0 text-center sm:w-16">
                                <p class="font-display text-[17px] font-bold leading-none text-ink">{{ $session->starts_at->format('H:i') }}</p>
                                <p class="mt-1 text-[11px] leading-none text-muted">ถึง {{ $session->ends_at->format('H:i') }}</p>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex gap-1" role="img" aria-label="ว่าง {{ $left }} จาก {{ $session->capacity }} ที่">
                                    @for ($i = 0; $i < $session->capacity; $i++)
                                        <span class="h-1.5 flex-1 rounded-full {{ $i < $session->booked_count ? 'bg-brand-light' : 'bg-track' }}"></span>
                                    @endfor
                                </div>
                                <p class="mt-1.5 text-[12px] {{ $left > 0 ? 'text-muted' : 'text-accent-ink' }}">
                                    {{ $left > 0 ? "ว่าง {$left} จาก {$session->capacity} ที่" : 'เต็มแล้ว' }}
                                </p>
                            </div>

                            <div class="shrink-0">
                                @if ($mine)
                                    <a href="{{ route('member.bookings') }}" wire:navigate
                                       class="chip {{ $mine->status === \App\Enums\BookingStatus::Pending ? 'bg-accent-light text-accent-ink' : 'bg-brand-light/40 text-brand-dark' }}">
                                        {{ $mine->status->label() }}
                                    </a>
                                @elseif ($left <= 0)
                                    <span class="chip bg-line/60 text-muted">เต็ม</span>
                                @elseif ($suspended)
                                    <span class="chip bg-line/60 text-muted">ถูกระงับ</span>
                                @else
                                    <button type="button" class="btn-primary px-5 py-2.5 text-[14px]"
                                            wire:loading.attr="disabled" wire:target="book({{ $session->id }})"
                                            @click="$store.confirm.ask({
                                                title: 'จองรอบนี้',
                                                body: @js($session->starts_at->locale('th')->isoFormat('dddd D MMM').' · '.$session->timeLabel()),
                                                notes: @js(array_values(array_filter([
                                                    $approval ? 'ส่งคำขอให้แอดมินยืนยัน ที่นั่งถูกกันไว้ให้ระหว่างรอ' : 'ได้ที่นั่งทันทีหลังกดยืนยัน',
                                                    $credits ? 'หักเครดิต 1 ครั้งจากแพ็กเกจของคุณ' : null,
                                                    'ถอนหรือยกเลิกได้ที่หน้าคิวของฉัน',
                                                ]))),
                                                tone: 'info',
                                                confirmLabel: @js($approval ? 'ส่งคำขอจอง' : 'ยืนยันการจอง'),
                                                cancelLabel: 'ยังก่อน',
                                                action: () => $wire.book({{ $session->id }}),
                                            })">
                                        จอง
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
