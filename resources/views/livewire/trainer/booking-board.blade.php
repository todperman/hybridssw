<div class="pb-16" wire:poll.60s>
    @php($trainer = $this->trainer())
    @php($summary = $this->daySummary)

    <x-page-hero
        :eyebrow="$trainer->branch->name"
        title="ตารางจอง" pattern="stopwatch" pattern-alt="rower"
        :subtitle="$trainer->type->label().' · จองได้ '.$trainer->maxSeatsPerSession().' ที่/รอบ · ล่วงหน้า '.$trainer->advanceBookingDays().' วัน'">

        <x-slot:action>
            <a href="{{ route('trainer.team') }}" wire:navigate
               class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/15 bg-white/10 px-5 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                <x-lucide-users class="h-4 w-4" stroke-width="1.9" />
                จัดการทีม
            </a>
        </x-slot:action>

        <x-slot:stats>
            <dl class="mt-6 grid grid-cols-3 gap-3 sm:max-w-md">
                @foreach ([
                    {{-- ป้ายต้องเปลี่ยนตามตัวกรอง ไม่งั้นตัวเลขจะอ่านผิดความหมาย --}}
                    [$onlyMine ? 'รอบของคุณวันนี้' : 'รอบวันนี้', $summary['sessions']],
                    ['ที่นั่งว่าง', $summary['openSeats']],
                    ['ลูกทีมของคุณ', $summary['mine']],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                        <dt class="text-[11px] leading-tight text-white/65">{{ $label }}</dt>
                        <dd class="mt-0.5 font-display text-2xl font-bold leading-none text-paper">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-slot:stats>
    </x-page-hero>

    {{-- สถานะบัญชี --}}
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (! $trainer->isApproved())
            <div class="mt-6 rounded-lg2 border border-accent bg-accent-light/70 p-5">
                <p class="font-display font-bold text-accent-ink">บัญชีของคุณยังจองไม่ได้</p>
                <p class="mt-1 text-sm text-accent-ink/85">
                    สถานะปัจจุบัน: {{ $trainer->status->label() }}
                    @if ($trainer->review_note) — {{ $trainer->review_note }} @endif
                </p>
            </div>
        @elseif ($trainer->hasCertificationExpired())
            <div class="mt-6 rounded-lg2 border border-red-200 bg-red-50 p-5">
                <p class="font-display font-bold text-red-800">ใบรับรองหมดอายุแล้ว</p>
                <p class="mt-1 text-sm text-red-700">กรุณาติดต่อแอดมินเพื่ออัปเดตเอกสารก่อนจองรอบใหม่</p>
            </div>
        @endif
    </div>

    {{-- แถบเลือกวันแบบสัปดาห์ ติดอยู่ใต้เมนูเวลาเลื่อนหน้า --}}
    <div class="sticky-under-nav z-30 border-b border-line bg-paper/85 backdrop-blur-md">
        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2">
                <button wire:click="shiftWeek(-1)" aria-label="สัปดาห์ก่อนหน้า"
                        class="glass-icon">
                    <x-lucide-chevron-left class="h-4 w-4" />
                </button>

                <div class="grid flex-1 grid-cols-7 gap-1 sm:gap-2">
                    @foreach ($this->weekDays as $day)
                        <button wire:click="selectDate('{{ $day['date'] }}')"
                                class="glass-day group
                                    {{ $day['isSelected'] ? 'glass-day-on' : '' }}
                                    {{ $day['isPast'] && ! $day['isSelected'] ? 'opacity-45' : '' }}">

                            {{-- จอเล็กใช้ตัวย่อเพราะพื้นที่จำกัด จอใหญ่ใช้ชื่อเต็มอ่านง่ายกว่า --}}
                            <span class="text-[11px] {{ $day['isSelected'] ? 'text-brand-deep' : 'text-muted' }}">
                                <span class="lg:hidden">{{ $day['label'] }}</span>
                                <span class="hidden lg:inline">{{ $day['labelFull'] }}</span>
                            </span>

                            <span class="font-display text-[17px] font-bold leading-tight {{ $day['isSelected'] ? 'text-brand-dark' : 'text-ink' }}">
                                {{ $day['day'] }}
                            </span>

                            {{-- จุดบอกว่าวันนั้นยังมีที่ว่างไหม อ่านได้เร็วกว่าตัวเลข --}}
                            <span class="mt-1 h-1.5 w-1.5 rounded-full
                                {{ $day['capacity'] === 0 ? 'bg-transparent' : ($day['open'] > 0 ? 'bg-brand' : 'bg-accent-deep') }}"></span>

                            @if ($day['isToday'])
                                <span class="absolute inset-x-3 top-1 h-0.5 rounded-full bg-brand-deep"></span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <button wire:click="shiftWeek(1)" aria-label="สัปดาห์ถัดไป"
                        class="glass-icon">
                    <x-lucide-chevron-right class="h-4 w-4" />
                </button>
            </div>

            {{-- มือถือแบ่งเป็นสองแถวคงที่ ไม่ปล่อยให้ไหลตกบรรทัดเอง
                 ปุ่มปฏิทินแสดงวันที่อยู่ในตัวแล้ว จึงไม่ต้องมีข้อความวันที่ซ้ำอีก --}}
            <div class="mt-2.5 space-y-2 sm:flex sm:items-center sm:justify-between sm:gap-3 sm:space-y-0">
                <div class="flex items-center gap-2">
                    <x-date-field model="date" class="min-w-0 flex-1 sm:flex-none" />

                    <button wire:click="goToday" class="glass-toggle min-h-[38px] shrink-0 text-brand-deep sm:min-h-[44px]">
                        วันนี้
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    {{-- กรองให้เหลือเฉพาะรอบที่มีลูกทีมของเรา --}}
                    <button wire:click="toggleOnlyMine"
                            aria-pressed="{{ $onlyMine ? 'true' : 'false' }}"
                            class="glass-toggle shrink-0 {{ $onlyMine ? 'glass-toggle-mine' : '' }}">
                        @if ($onlyMine)
                            <x-lucide-check class="h-3.5 w-3.5" stroke-width="2.6" />
                        @else
                            <x-lucide-list-filter class="h-3.5 w-3.5" stroke-width="2.2" />
                        @endif
                        การจองของฉัน
                    </button>

                    {{-- สลับระหว่างไทม์ไลน์รายวันกับตารางทั้งสัปดาห์ --}}
                    <div class="glass-seg ms-auto shrink-0 sm:ms-0">
                        @foreach (\App\Livewire\Trainer\BookingBoard::VIEWS as $key => $label)
                            <button wire:key="view-{{ $key }}" wire:click="setView('{{ $key }}')"
                                    class="glass-seg-btn {{ $view === $key ? 'glass-seg-btn-on' : '' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- รายการรอบ --}}
    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        {{-- โครงร่างระหว่างสลับวัน ลดความรู้สึกกระตุก --}}
        <div wire:loading.flex wire:target="selectDate,shiftWeek,goToToday,date" class="hidden flex-col gap-3">
            @for ($i = 0; $i < 5; $i++)
                <div class="flex gap-3 sm:gap-5">
                    <div class="w-14 shrink-0 sm:w-20"></div>
                    <div class="card h-[132px] flex-1 animate-pulse bg-white/50"></div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove wire:target="selectDate,shiftWeek,goToToday,date">
            @if ($view === 'grid')
                @php($wg = $this->weekGrid)

                {{-- ตารางทั้งสัปดาห์ แถวคือชั่วโมง คอลัมน์คือวันที่
                     ใช้หาช่องว่างข้ามวันได้เร็วกว่าไล่ดูทีละวัน --}}
                @if ($onlyMine && empty($wg['hours']))
                    <div class="card px-6 py-16 text-center">
                        <p class="font-display text-lg font-bold text-ink">สัปดาห์นี้คุณยังไม่ได้จองรอบไหน</p>
                        <p class="mt-1 text-sm text-muted">ปิดตัวกรองเพื่อดูรอบทั้งหมด</p>
                        <button wire:click="toggleOnlyMine" class="btn-ghost mt-4">แสดงทุกรอบ</button>
                    </div>
                @else
                <div class="card">
                    <div class="wg-scroll rounded-lg2">
                        <table class="wg-table sm:min-w-[720px]">
                            <thead>
                                <tr>
                                    <th class="w-6 sm:w-12"></th>
                                    @foreach ($wg['days'] as $d)
                                        <th>
                                            <button wire:click="selectDate('{{ $d['date'] }}')"
                                                    class="glass-day mx-auto min-h-[40px] w-full rounded-lg px-0.5 py-1
                                                        sm:min-h-[44px] sm:max-w-[84px] sm:rounded-xl sm:px-1
                                                        {{ $d['date'] === $date ? 'glass-day-on' : '' }}">
                                                <span class="text-[10px] leading-none sm:text-[11px] {{ $d['date'] === $date ? 'text-brand-deep' : 'text-muted' }}">
                                                    <span class="lg:hidden">{{ $d['label'] }}</span>
                                                    <span class="hidden lg:inline">{{ $d['labelFull'] }}</span>
                                                </span>
                                                <span class="font-display text-[14px] font-bold leading-tight sm:text-[15px] {{ $d['date'] === $date ? 'text-brand-dark' : 'text-ink' }}">{{ $d['day'] }}</span>
                                                @if ($d['isToday'])
                                                    <span class="mt-0.5 h-1 w-3 rounded-full bg-brand-deep sm:w-4"></span>
                                                @endif
                                            </button>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($wg['hours'] as $hr)
                                    <tr>
                                        <th class="pe-1 text-end align-middle text-[10px] font-semibold tabular-nums leading-none text-muted sm:pe-1.5 sm:text-[11px]">
                                            {{ str_pad($hr, 2, '0', STR_PAD_LEFT) }}
                                        </th>

                                        @foreach ($wg['days'] as $d)
                                            @php($cellSession = $wg['cells'][$hr][$d['date']] ?? null)

                                            <td class="align-top">
                                                @if (! $cellSession)
                                                    <div class="h-[50px] rounded-lg border border-dashed border-line sm:h-[58px] sm:rounded-xl"></div>
                                                @else
                                                    @php($cMine = $cellSession->activeBookings->where('trainer_id', $trainer->id)->count())
                                                    @php($cUsed = $cellSession->booked_count)
                                                    @php($cFree = $cellSession->seatsRemaining())
                                                    @php($cOpen = $cellSession->isBookable() && ! $cellSession->isLockedForTrainer($trainer) && $trainer->isApproved())

                                                    <button type="button"
                                                            @if ($cOpen) wire:click="openSession({{ $cellSession->id }})" @else disabled @endif
                                                            class="glass-day h-[50px] min-h-0 w-full gap-1 rounded-lg px-1
                                                                sm:h-[58px] sm:gap-1.5 sm:rounded-xl sm:px-1.5
                                                                {{ $cMine > 0 ? 'glass-day-mine' : '' }}
                                                                {{ $cOpen ? 'cursor-pointer' : 'cursor-default opacity-60' }}">

                                                        <span class="text-[11px] font-bold tabular-nums leading-none sm:text-[12px] {{ $cMine > 0 ? 'text-mine-ink' : 'text-ink' }}">
                                                            {{ $cUsed }}/{{ $cellSession->capacity }}
                                                        </span>

                                                        <span class="flex w-full gap-px sm:gap-0.5">
                                                            @for ($i = 0; $i < $cellSession->capacity; $i++)
                                                                <span class="h-[3px] flex-1 rounded-full sm:h-1
                                                                    {{ $i < $cMine ? 'bg-mine' : ($i < $cUsed ? 'bg-brand-light' : 'bg-track') }}"></span>
                                                            @endfor
                                                        </span>
                                                    </button>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line px-5 py-3 text-[11px] text-muted">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-mine"></span>ลูกทีมของคุณ</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-light"></span>ผู้เล่นอื่น</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-track ring-1 ring-line"></span>ว่าง</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded border border-dashed border-line"></span>ไม่เปิดรอบ</span>
                        <span class="ml-auto">แตะช่องเพื่อจอง</span>
                    </div>
                </div>
                @endif
            @elseif ($this->sessions->isEmpty())
                <div class="card px-6 py-16 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="stopwatch" class="h-7 w-7 text-brand-deep" />
                    </span>
                    @if ($onlyMine)
                        <p class="mt-4 font-display text-lg font-bold text-ink">วันนี้คุณยังไม่ได้จองรอบไหน</p>
                        <p class="mt-1 text-sm text-muted">ปิดตัวกรองเพื่อดูรอบทั้งหมดที่เปิดจองอยู่</p>
                        <button wire:click="toggleOnlyMine" class="btn-ghost mt-4">แสดงทุกรอบ</button>
                    @else
                        <p class="mt-4 font-display text-lg font-bold text-ink">วันนี้ไม่มีรอบเปิดให้จอง</p>
                        <p class="mt-1 text-sm text-muted">ลองเลือกวันอื่นจากแถบด้านบน</p>
                    @endif
                </div>
            @else
                <div class="space-y-8">
                    @foreach ($this->groupedSessions as $period)
                        <section>
                            {{-- หัวช่วงเวลา ช่วยให้กวาดตาหาช่วงที่ต้องการได้เร็วกว่าไล่ดูทีละรอบ --}}
                            <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h2 class="font-display text-lg font-bold text-ink">{{ $period['label'] }}</h2>
                                <span class="text-[13px] text-muted">{{ $period['hint'] }}</span>
                                <span class="chip ml-auto {{ $period['openSeats'] > 0 ? 'bg-brand-light/50 text-brand-dark' : 'bg-line/60 text-muted' }}">
                                    ว่างรวม {{ $period['openSeats'] }} ที่
                                </span>
                            </div>

                            <ul class="space-y-3">
                                @foreach ($period['sessions'] as $session)
                                    @php($mine = $session->activeBookings->where('trainer_id', $trainer->id))
                                    @php($others = $session->activeBookings->where('trainer_id', '!=', $trainer->id))
                                    @php($locked = $session->isLockedForTrainer($trainer))
                                    @php($remaining = $session->seatsRemaining())
                                    @php($bookable = $session->isBookable() && ! $locked && $trainer->isApproved())
                                    @php($passed = $session->hasStarted())

                                    <li class="relative flex gap-3 sm:gap-5">
                                        {{-- รางเวลาด้านซ้าย --}}
                                        <div class="flex w-14 shrink-0 flex-col items-center pt-4 sm:w-20">
                                            <span class="font-display text-[15px] font-bold leading-none {{ $passed ? 'text-muted' : 'text-ink' }}">
                                                {{ $session->starts_at->format('H:i') }}
                                            </span>
                                            <span class="mt-1 text-[11px] leading-none text-muted">
                                                {{ $session->ends_at->format('H:i') }}
                                            </span>

                                            <span class="mt-2.5 h-2.5 w-2.5 rounded-full ring-4 ring-paper
                                                {{ $passed ? 'bg-line' : ($remaining > 0 && $session->isBookable() ? 'bg-brand' : 'bg-accent-deep') }}"></span>

                                            @if (! $loop->last)
                                                <span class="mt-1 w-px flex-1 bg-line"></span>
                                            @endif
                                        </div>

                                        <article class="card flex-1 overflow-hidden transition duration-300 ease-out-soft hover:shadow-lift
                                                        {{ $passed ? 'opacity-60' : '' }}">
                                            <div class="space-y-3.5 p-4 sm:p-5">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="font-display text-lg font-bold text-ink">{{ $session->timeLabel() }}</span>

                                                    <span class="chip {{ $remaining > 0 ? 'bg-brand-light/50 text-brand-dark' : 'bg-line/60 text-muted' }}">
                                                        ว่าง {{ $remaining }}/{{ $session->capacity }}
                                                    </span>
                                                </div>

                                                @php($mineCount = $mine->count())
                                                @php($otherCount = $others->count())

                                                {{-- แถบที่นั่งแยกสามสถานะ ให้เห็นว่ารอบนี้ถูกใช้ไปเท่าไหร่
                                                     โดยไม่ต้องเปิดเผยว่าที่นั่งของคนอื่นเป็นของใคร --}}
                                                <div class="flex gap-1.5" role="img"
                                                     aria-label="ลูกทีมของคุณ {{ $mineCount }} ที่ · ผู้เล่นอื่น {{ $otherCount }} ที่ · ว่าง {{ $remaining }} ที่ จากทั้งหมด {{ $session->capacity }}">
                                                    @for ($i = 0; $i < $session->capacity; $i++)
                                                        <span class="h-1.5 flex-1 rounded-full
                                                            {{ $i < $mineCount ? 'bg-mine' : ($i < $mineCount + $otherCount ? 'bg-brand-light' : 'bg-track') }}"></span>
                                                    @endfor
                                                </div>

                                                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="h-2 w-2 rounded-full bg-mine"></span>ของคุณ {{ $mineCount }}
                                                    </span>
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="h-2 w-2 rounded-full bg-brand-light"></span>ผู้เล่นอื่น {{ $otherCount }}
                                                    </span>
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="h-2 w-2 rounded-full bg-track ring-1 ring-line"></span>ว่าง {{ $remaining }}
                                                    </span>
                                                </p>

                                                @if (! $session->status->acceptsBookings())
                                                    <p class="text-sm text-red-700">{{ $session->status->label() }}{{ $session->close_reason ? " — {$session->close_reason}" : '' }}</p>
                                                @elseif ($locked)
                                                    <p class="text-sm text-accent-ink">รอบนี้ถูกเทรนเนอร์ท่านอื่นเหมาไปแล้ว</p>
                                                @endif

                                                @if ($mine->isNotEmpty())
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-mine-ink">ลูกทีมของคุณ</p>
                                                    {{-- ทำเป็นป้ายเรียงต่อกันแทนแถวเต็มความกว้าง
                                                         ชื่อคนสั้น ๆ ไม่คุ้มที่จะกินทั้งบรรทัด และพอขึ้นบรรทัดใหม่เองได้
                                                         การ์ดจึงสั้นลงมากและพอดีกับจอมือถือโดยไม่ต้องมีเลย์เอาต์แยก --}}
                                                    <ul class="flex flex-wrap gap-1.5">
                                                        @foreach ($mine as $booking)
                                                            <li class="flex max-w-full items-center gap-1.5 rounded-full border border-mine/25 bg-mine-soft/60 py-1 pe-1 ps-1.5">
                                                                <x-avatar :user="$booking->member->user" size="h-6 w-6" text="text-[10px]" />

                                                                <span class="truncate text-[13px] font-medium text-mine-ink">{{ $booking->member->user->name }}</span>

                                                                @if ($booking->confirm_deadline_at)
                                                                    <span class="chip shrink-0 bg-accent-light text-[10px] text-accent-ink">รอยืนยัน {{ $booking->confirm_deadline_at->format('H:i') }}</span>
                                                                @endif

                                                                @if ($session->isBookable())
                                                                    <button type="button" aria-label="ยกเลิกการจองของ {{ $booking->member->user->name }}"
                                                                            class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-mine-ink/60 transition hover:bg-white/70 hover:text-red-700"
                                                                            @click="$store.confirm.ask({
                                                                                title: 'ยกเลิกการจอง',
                                                                                body: '{{ $booking->member->user->name }} · {{ $session->timeLabel() }}',
                                                                                notes: @js($booking->wouldBeLateCancellation()
                                                                                    ? ['เลยกำหนดยกเลิกฟรีแล้ว เครดิตจะถูกหัก', 'ที่นั่งจะถูกปล่อยให้คิวสำรองทันที']
                                                                                    : ['ยกเลิกทันเวลา เครดิตจะถูกคืนให้ลูกทีม', 'ที่นั่งจะถูกปล่อยให้คิวสำรองทันที']),
                                                                                tone: 'danger',
                                                                                confirmLabel: 'ยกเลิกการจอง',
                                                                                cancelLabel: 'เก็บไว้ก่อน',
                                                                                action: () => $wire.cancelBooking({{ $booking->id }}),
                                                                            })">
                                                                        <x-lucide-x class="h-3.5 w-3.5" stroke-width="2.4" />
                                                                    </button>
                                                                @endif
                                                            </li>
                                                        @endforeach

                                                        @if ($otherCount > 0)
                                                            {{-- ไม่แสดงชื่อลูกทีมหรือเทรนเนอร์ของคนอื่น บอกแค่จำนวนที่นั่งที่ถูกใช้ --}}
                                                            <li class="flex items-center gap-1.5 rounded-full border border-line bg-white/70 py-1 pe-3 ps-1.5">
                                                                <span class="flex -space-x-1.5">
                                                                    @for ($i = 0; $i < min($otherCount, 3); $i++)
                                                                        <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-light/50 ring-2 ring-white">
                                                                            <x-lucide-user class="h-3 w-3 text-brand-dark/70" />
                                                                        </span>
                                                                    @endfor
                                                                </span>
                                                                <span class="text-[13px] text-muted">ผู้เล่นอื่น {{ $otherCount }}</span>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                @endif

                                                @if ($mine->isEmpty() && $otherCount > 0)
                                                    <div class="flex items-center gap-2 rounded-full border border-line bg-white/70 py-1 pe-3 ps-1.5">
                                                        <span class="flex -space-x-1.5">
                                                            @for ($i = 0; $i < min($otherCount, 3); $i++)
                                                                <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-light/50 ring-2 ring-white">
                                                                    <x-lucide-user class="h-3 w-3 text-brand-dark/70" />
                                                                </span>
                                                            @endfor
                                                        </span>
                                                        <span class="text-[13px] text-muted">ผู้เล่นอื่น {{ $otherCount }} ที่นั่ง</span>
                                                    </div>
                                                @endif

                                                @if ($bookable)
                                                    {{-- วางตรงกลางและไม่เต็มความกว้าง เพื่อให้การ์ดไม่ถูกปุ่มครอบงำ
                                                         แต่ยังสูง 44px บนมือถือให้แตะง่าย --}}
                                                    <div class="flex justify-center pt-1">
                                                        <button wire:click="openSession({{ $session->id }})"
                                                                class="btn-grad px-6 text-[14px] sm:min-h-[38px] sm:px-5 sm:text-[13px]">
                                                            {{ $remaining > 0 ? 'จองรอบนี้' : 'ต่อคิวสำรอง' }}
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </article>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- กล่องเลือกลูกทีม --}}
    @if ($this->selectedSession)
        @php($s = $this->selectedSession)
        @php($picked = count($selectedMemberIds))
        @php($free = $s->seatsRemaining())
        @php($after = max(0, $free - $picked))
        @php($over = max(0, $picked - $free))

        <div class="modal-scrim z-[80]"
             wire:key="modal-{{ $selectedSessionId }}"
             @keydown.escape.window="! $store.confirm.open && $wire.closeSession()">

            <div class="glass-panel modal-panel">
                <div class="flex justify-center pt-2.5 sm:hidden">
                    <span class="h-1 w-10 rounded-full bg-ink/15"></span>
                </div>

                <div class="glass-head flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-deep">
                            {{ $s->starts_at->locale('th')->isoFormat('dddd D MMM') }}
                        </p>
                        <h3 class="mt-0.5 font-display text-[22px] font-extrabold leading-tight text-grad">
                            {{ $s->timeLabel() }}
                        </h3>
                    </div>

                    {{-- วงแหวนที่นั่ง เห็นสัดส่วนที่ว่างได้ทันทีโดยไม่ต้องอ่านตัวเลข --}}
                    <div class="flex shrink-0 items-center gap-3">
                        @php($pct = $s->capacity > 0 ? round(($s->capacity - $free) / $s->capacity * 100) : 0)
                        <div class="relative grid h-12 w-12 place-items-center rounded-full"
                             style="background: conic-gradient(var(--brand-light) {{ $pct * 3.6 }}deg, rgba(28,47,58,.10) 0deg)">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-white/90 font-display text-[13px] font-bold text-brand-dark">
                                {{ $free }}
                            </span>
                        </div>

                        <button wire:click="closeSession" aria-label="ปิด"
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-muted transition hover:bg-white/70 hover:text-ink">
                            <x-lucide-x class="h-4 w-4" stroke-width="2.2" />
                        </button>
                    </div>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    @if ($lastResult)
                        <div class="space-y-1.5 rounded-2xl border border-white/60 bg-white/60 p-3.5 text-sm backdrop-blur-sm">
                            @foreach ($lastResult['booked'] ?? [] as $ok)
                                <p class="flex items-center gap-2 text-brand-dark">
                                    <x-lucide-check class="h-4 w-4 shrink-0 text-brand" stroke-width="2.4" />
                                    {{ $ok['name'] }} — {{ $ok['status'] }}
                                </p>
                            @endforeach
                            @foreach ($lastResult['failed'] ?? [] as $bad)
                                <p class="flex items-center gap-2 text-red-700">
                                    <x-lucide-x class="h-4 w-4 shrink-0" stroke-width="2.4" />
                                    {{ $bad['name'] }} — {{ $bad['reason'] }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    @if ($this->team->isEmpty())
                        <div class="rounded-2xl border border-white/60 bg-white/55 p-8 text-center backdrop-blur-sm">
                            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-white/70">
                                <x-station-icon name="dumbbell" class="h-7 w-7 text-brand-deep" />
                            </span>
                            <p class="mt-3 text-sm text-muted">ยังไม่มีลูกทีม</p>
                            <a href="{{ route('trainer.team') }}" wire:navigate class="btn-ghost mt-3">ไปหน้าชวนลูกทีม</a>
                        </div>
                    @else
                        @if ($this->groups->isNotEmpty())
                            <div>
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">เลือกทั้งกลุ่ม</p>

                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->groups as $group)
                                        @php($gc = $group->palette())
                                        <button type="button" wire:click="applyGroup({{ $group->id }})" class="glass-pill">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $gc['base'] }}"></span>
                                            <span class="font-medium">{{ $group->name }}</span>
                                            <span class="chip text-[11px]" style="background: {{ $gc['soft'] }}; color: {{ $gc['ink'] }}">{{ $group->members_count }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="space-y-1.5">
                            @foreach ($this->team as $member)
                                @php($taken = in_array($member->id, $this->alreadyBookedMemberIds))
                                @php($on = in_array($member->id, $selectedMemberIds))

                                <label class="glass-row {{ $taken ? 'glass-row-off' : 'cursor-pointer' }} {{ $on ? 'glass-row-on' : '' }}">
                                    <input type="checkbox" wire:model.live="selectedMemberIds" value="{{ $member->id }}"
                                           @disabled($taken) class="h-5 w-5 rounded-md border-line/80 bg-white/70 text-brand-deep focus:ring-brand-deep/30">

                                    <x-avatar :user="$member->user" size="h-9 w-9" text="text-[11px]" class="ring-2 ring-white/80" />

                                    <span class="flex-1 truncate text-sm font-medium text-ink">{{ $member->user->name }}</span>

                                    <span class="chip shrink-0 {{ $taken ? 'bg-ink/10 text-muted' : 'bg-accent-light/80 text-accent-ink' }}">
                                        {{ $taken ? 'จองแล้ว' : 'เครดิต '.$member->availableCredits() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <label class="glass-row cursor-pointer">
                            <input type="checkbox" wire:model.live="allowWaitlist"
                                   class="h-5 w-5 rounded-md border-line/80 bg-white/70 text-brand-deep focus:ring-brand-deep/30">
                            <span class="flex-1 text-[13px] text-ink">ถ้าที่นั่งเต็ม ให้ต่อคิวสำรองอัตโนมัติ</span>
                        </label>
                    @endif
                </div>

                <div class="glass-foot">
                    {{-- บอกผลลัพธ์ก่อนกดจอง จะได้ไม่ต้องเดาว่าที่นั่งพอไหม --}}
                    @if ($picked > 0)
                        <p class="mb-3 text-center text-[13px] text-muted">
                            เลือกไว้ <span class="font-semibold text-ink">{{ $picked }}</span> คน ·
                            @if ($over > 0)
                                <span class="font-semibold text-accent-ink">เกินที่นั่ง {{ $over }} คน</span>
                            @else
                                จองแล้วจะเหลือ <span class="font-semibold text-ink">{{ $after }}</span> ที่
                            @endif
                        </p>
                    @endif

                    <div class="flex gap-2">
                        <button wire:click="closeSession" class="btn-ghost">ปิด</button>

                        {{-- การจองหักเครดิตของลูกทีมจริง จึงถามยืนยันเหมือนการยกเลิก
                             อ่านค่าจาก $wire เพราะเป็นค่าฝั่งไคลเอนต์ที่อัปเดตทันที --}}
                        <button type="button" wire:loading.attr="disabled"
                                @disabled($picked === 0)
                                class="btn-grad flex-1"
                                @click="$store.confirm.ask({
                                    title: 'ยืนยันการจอง',
                                    body: @js($s->starts_at->locale('th')->isoFormat('dddd D MMM').' · '.$s->timeLabel()),
                                    notes: [
                                        'จองให้ลูกทีม ' + $wire.selectedMemberIds.length + ' คน',
                                        'หักเครดิตของลูกทีมคนละ 1 ครั้ง',
                                        $wire.allowWaitlist
                                            ? 'ถ้าที่นั่งเต็ม ระบบจะต่อคิวสำรองให้อัตโนมัติ'
                                            : 'ถ้าที่นั่งเต็ม รายการนั้นจะจองไม่สำเร็จ',
                                    ],
                                    tone: 'info',
                                    confirmLabel: 'ยืนยันจอง',
                                    cancelLabel: 'กลับไปแก้',
                                    action: () => $wire.book(),
                                })">
                            <span wire:loading.remove wire:target="book" class="relative">ยืนยันจอง {{ $picked ?: '' }}</span>
                            <span wire:loading wire:target="book" class="relative flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                                </svg>
                                กำลังจอง…
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
