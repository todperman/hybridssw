<div class="pb-16">
    @php($branch = $this->branch)
    @php($trainee = $mode === 'trainee')
    @php($monthStart = $this->monthStart)
    @php($days = $this->days)
    @php($selected = \Carbon\CarbonImmutable::parse($date))
    @php($slots = $this->slots)
    @php($maxHours = max(1, (int) $branch->max_booking_hours))
    @php($dayInfo = $days[$date] ?? null)

    <x-page-hero eyebrow="ปฏิทิน" title="ว่างวันไหน เวลาไหน" :subtitle="$branch->name" pattern="stopwatch" pattern-alt="rower">
        <x-slot:action>
            <div class="flex flex-wrap gap-2">
                <x-price-sheet :branch="$branch" />
                <a href="{{ route($trainee ? 'member.book' : 'trainer.book') }}" wire:navigate
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                    @svg('lucide-plus', 'h-4 w-4', ['stroke-width' => '2'])
                    {{ $trainee ? 'จองยิม' : 'จองให้ลูกเทรน' }}
                </a>
            </div>
        </x-slot:action>
        <x-slot:stats>
            <p class="mt-4 flex max-w-md items-start gap-2 text-[13px] leading-relaxed text-white/80">
                @svg('lucide-info', 'mt-0.5 h-4 w-4 shrink-0 text-brand-light', ['stroke-width' => '2'])
                หนึ่งการจองได้ใช้ทั้งยิม แตะวันแล้วเลือกชั่วโมงที่ว่าง จองต่อเนื่องได้สูงสุด {{ $maxHours }} ชั่วโมง
            </p>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">

            {{-- เดือน --}}
            <section class="card overflow-hidden self-start">
                <div class="card-head flex items-center justify-between gap-2">
                    <button type="button" wire:click="shiftMonth(-1)" @disabled(! $this->canGoBack) aria-label="เดือนก่อนหน้า"
                            class="grid h-9 w-9 place-items-center rounded-full text-ink transition hover:bg-white/70 disabled:opacity-30">
                        @svg('lucide-chevron-left', 'h-5 w-5', ['stroke-width' => '2.2'])
                    </button>
                    <h2 class="font-display text-[17px] font-bold text-grad">{{ $monthStart->locale('th')->isoFormat('MMMM YYYY') }}</h2>
                    <button type="button" wire:click="shiftMonth(1)" @disabled(! $this->canGoForward) aria-label="เดือนถัดไป"
                            class="grid h-9 w-9 place-items-center rounded-full text-ink transition hover:bg-white/70 disabled:opacity-30">
                        @svg('lucide-chevron-right', 'h-5 w-5', ['stroke-width' => '2.2'])
                    </button>
                </div>

                <div class="p-3 sm:p-4">
                    <div class="mb-1 grid grid-cols-7 text-center text-[11px] font-medium text-muted">
                        @foreach (['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'] as $dow)
                            <span>{{ $dow }}</span>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-7 gap-1" role="grid">
                        @foreach (\App\Services\Reservations\CalendarService::gridDays($monthStart) as $d)
                            @php($ymd = $d->toDateString())
                            @php($info = $days[$ymd] ?? null)
                            @php($inMonth = $d->month === $monthStart->month)
                            @php($dead = ! $inMonth || ! $info || $info['past'] || $info['beyond'] || $info['closed'])
                            @php($ratio = $info && $info['open'] > 0 ? $info['free'] / $info['open'] : 0)
                            @php($tone = match (true) {
                                $dead => 'bg-transparent',
                                $info['free'] === 0 => 'bg-line',
                                $ratio >= 0.5 => 'bg-emerald-400',
                                default => 'bg-accent',
                            })
                            @php($active = $ymd === $date)

                            <button type="button" role="gridcell" wire:click="selectDay('{{ $ymd }}')" wire:key="cal-{{ $ymd }}"
                                    @disabled($dead) aria-selected="{{ $active ? 'true' : 'false' }}"
                                    aria-label="{{ $d->locale('th')->isoFormat('D MMMM') }}{{ $info && ! $dead ? ' ว่าง '.$info['free'].' ชั่วโมง' : '' }}"
                                    class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl text-sm transition
                                           {{ $active ? 'bg-brand-dark text-white shadow-soft' : ($dead ? 'text-muted/40' : 'text-ink hover:bg-mist') }}
                                           {{ ! $inMonth ? 'invisible' : '' }}">
                                <span class="font-display text-[15px] font-bold leading-none {{ $d->isToday() && ! $active ? 'text-brand-deep' : '' }}">{{ $d->day }}</span>
                                @if ($info && ! $dead)
                                    <span class="text-[10px] leading-none {{ $active ? 'text-white/80' : 'text-muted' }}">{{ $info['free'] }} ชม.</span>
                                    <span class="h-1 w-5 rounded-full {{ $active ? 'bg-white/70' : $tone }}"></span>
                                @else
                                    <span class="h-[10px]"></span>
                                    <span class="h-1 w-5"></span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted">
                        <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-emerald-400"></span> ว่างมาก</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-accent"></span> เหลือน้อย</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-line"></span> เต็ม</span>
                        <span class="inline-flex items-center gap-1.5 text-muted/60">ตัวจาง = ปิด / ผ่านไปแล้ว / เกิน {{ $branch->booking_window_days }} วัน</span>
                    </div>
                </div>
            </section>

            {{-- วัน --}}
            <section class="card overflow-hidden self-start">
                <div class="card-head flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-[17px] font-bold text-grad">{{ $selected->locale('th')->isoFormat('dddd D MMMM') }}</h2>
                        @if ($dayInfo && ! $dayInfo['closed'])
                            <p class="mt-0.5 text-xs text-muted">เปิด {{ $dayInfo['open'] }} ชม. · ว่าง {{ $dayInfo['free'] }} ชม.{{ $dayInfo['longest'] > 1 ? ' · ว่างต่อเนื่องสูงสุด '.$dayInfo['longest'].' ชม.' : '' }}</p>
                        @endif
                    </div>
                    @if ($selected->isToday())
                        <span class="chip bg-brand-light/40 text-brand-dark">วันนี้</span>
                    @endif
                </div>

                @if ($slots === [])
                    <div class="px-5 py-12 text-center">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                            <x-station-icon name="stopwatch" class="h-7 w-7 text-brand-deep" />
                        </span>
                        <p class="mt-4 font-display text-lg font-bold text-ink">วันนี้ยิมปิด</p>
                        <p class="mt-1 text-sm text-muted">เลือกวันอื่นจากปฏิทิน</p>
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($slots as $slot)
                            @php($hm = $slot['start']->format('H:i'))
                            @php($isPicked = $picked === $hm)
                            @php($bookable = $slot['status'] === 'free' && ! ($dayInfo['beyond'] ?? false))
                            <li wire:key="slot-{{ $date }}-{{ $hm }}" class="{{ $isPicked ? 'bg-brand-light/15' : '' }}">
                                <div class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                    <div class="w-14 shrink-0">
                                        <p class="font-display text-[16px] font-bold leading-none {{ $slot['status'] === 'past' ? 'text-muted/50' : 'text-ink' }}">{{ $hm }}</p>
                                        <p class="mt-1 text-[11px] leading-none text-muted">ถึง {{ $slot['start']->addHour()->format('H:i') }}</p>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        @if ($slot['status'] === 'mine')
                                            <a href="{{ route('reservations.show', $slot['reference']) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-deep">
                                                @svg('lucide-ticket', 'h-4 w-4') การจองของคุณ
                                            </a>
                                        @elseif ($slot['status'] === 'taken')
                                            <span class="text-sm text-muted">จองแล้ว</span>
                                        @elseif ($slot['status'] === 'past')
                                            <span class="text-sm text-muted/60">ผ่านไปแล้ว</span>
                                        @else
                                            <span class="text-sm font-medium text-emerald-700">ว่าง</span>
                                            <span class="block text-[12px] text-muted">
                                                {{ $slot['trainers'] > 0 ? 'Trainer ว่าง '.$slot['trainers'].' คน' : 'ไม่มี Trainer ว่าง' }}
                                                {{ $slot['run'] > 1 ? '· ต่อเนื่องได้ '.min($slot['run'], $maxHours).' ชม.' : '' }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($bookable)
                                        <button type="button" wire:click="pick('{{ $hm }}')" aria-expanded="{{ $isPicked ? 'true' : 'false' }}"
                                                class="{{ $isPicked ? 'btn-ghost' : 'btn-primary' }} shrink-0 px-4 py-2 text-sm">
                                            {{ $isPicked ? 'ยกเลิก' : 'จอง' }}
                                        </button>
                                    @endif
                                </div>

                                @if ($isPicked && $bookable)
                                    <div class="flex flex-wrap items-center gap-2 border-t border-line/60 bg-white/60 px-4 py-3 sm:px-5">
                                        <span class="text-sm text-muted">เริ่ม {{ $hm }} จองกี่ชั่วโมง?</span>
                                        @for ($h = 1; $h <= min($slot['run'], $maxHours); $h++)
                                            <button type="button" wire:click="book('{{ $hm }}', {{ $h }})" wire:key="book-{{ $hm }}-{{ $h }}"
                                                    class="btn-grad min-h-[40px] px-4 text-sm font-semibold">
                                                {{ $h }} ชม. <span class="font-normal opacity-70">ถึง {{ $slot['start']->addHours($h)->format('H:i') }}</span>
                                            </button>
                                        @endfor
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>
