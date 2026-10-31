<x-filament-panels::page>
    @php($monthStart = $this->monthStart)
    @php($byDay = $this->byDay)
    @php($selected = \Carbon\CarbonImmutable::parse($date))
    @php($summary = $this->monthSummary())
    @php($branches = $this->branches())
    @php($statusClass = fn ($r) => match ($r->status) {
        \App\Enums\ReservationStatus::Confirmed => 'is-confirmed',
        \App\Enums\ReservationStatus::PendingPayment => 'is-pending',
        \App\Enums\ReservationStatus::Cancelled => 'is-cancelled',
        default => 'is-expired',
    })

    {{-- ตัวกรอง --}}
    <div class="rc-toolbar">
        @if ($branches->count() > 1)
            <x-filament::input.wrapper class="rc-filter">
                <x-filament::input.select wire:model.live="branchId">
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif

        <x-filament::input.wrapper class="rc-filter">
            <x-filament::input.select wire:model.live="trainerId">
                <option value="">Trainer ทุกคน</option>
                @foreach ($this->trainers as $t)
                    <option value="{{ $t->id }}">{{ $t->user?->name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <label class="rc-check">
            <x-filament::input.checkbox wire:model.live="showClosed" />
            <span>แสดงที่ยกเลิก/หมดอายุ</span>
        </label>

        <div class="rc-legend">
            <span><i class="is-confirmed"></i>ยืนยันแล้ว</span>
            <span><i class="is-pending"></i>รอชำระ</span>
            <span><i class="is-cancelled"></i>ยกเลิก</span>
            <span><i class="is-expired"></i>หมดอายุ</span>
        </div>
    </div>

    <div class="rc-layout">
        {{-- เดือน --}}
        <section class="rc-card">
            <header class="rc-head">
                <button type="button" wire:click="shiftMonth(-1)" class="rc-nav" aria-label="เดือนก่อนหน้า">‹</button>
                <div class="rc-head__title">
                    <h2>{{ $monthStart->locale('th')->isoFormat('MMMM YYYY') }}</h2>
                    <p>ยืนยันแล้ว {{ $summary['count'] }} รายการ · รอชำระ {{ $summary['pending'] }} · รวม {{ $summary['hours'] }} ชม. · ยอดยืนยัน ฿{{ number_format($summary['amount']) }}</p>
                </div>
                <button type="button" wire:click="shiftMonth(1)" class="rc-nav" aria-label="เดือนถัดไป">›</button>
            </header>

            <div class="rc-dow">
                @foreach (['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'] as $dow)
                    <span>{{ $dow }}</span>
                @endforeach
            </div>

            <div class="rc-grid" role="grid">
                @foreach (static::gridDays($monthStart) as $d)
                    @php($ymd = $d->toDateString())
                    @php($items = $byDay->get($ymd, collect()))
                    @php($live = $items->filter(fn ($r) => $r->status->holdsSlots()))
                    @php($inMonth = $d->month === $monthStart->month)
                    <button type="button" role="gridcell" wire:click="selectDay('{{ $ymd }}')" wire:key="rc-{{ $ymd }}"
                            class="rc-day {{ $inMonth ? '' : 'is-out' }} {{ $ymd === $date ? 'is-selected' : '' }} {{ $d->isToday() ? 'is-today' : '' }} {{ $d->lt(today()) ? 'is-past' : '' }}"
                            aria-selected="{{ $ymd === $date ? 'true' : 'false' }}">
                        <span class="rc-day__num">{{ $d->day }}@if ($live->isNotEmpty())<b>{{ $live->count() }}</b>@endif</span>
                        <span class="rc-day__list">
                            @foreach ($items->take(3) as $r)
                                <span class="rc-chip {{ $statusClass($r) }}" title="{{ $r->timeLabel() }} {{ $r->payer?->user?->name }}">
                                    {{ $r->starts_at->format('H:i') }} {{ \Illuminate\Support\Str::limit($r->payer?->user?->nickname ?: $r->payer?->user?->first_name ?? '-', 8, '') }}
                                </span>
                            @endforeach
                            @if ($items->count() > 3)
                                <span class="rc-more">+{{ $items->count() - 3 }} รายการ</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- วัน --}}
        <section class="rc-card">
            <header class="rc-head rc-head--day">
                <div class="rc-head__title">
                    <h2>{{ $selected->locale('th')->isoFormat('dddd D MMMM YYYY') }}</h2>
                    @php($dayLive = $this->dayList->filter(fn ($r) => $r->status->holdsSlots()))
                    <p>{{ $dayLive->count() }} รายการ · {{ (int) $dayLive->sum('hours') }} ชม. · ฿{{ number_format($dayLive->sum('amount')) }}{{ $this->branch ? ' · '.$this->branch->name : '' }}</p>
                </div>
                @if ($selected->isToday())<span class="rc-badge">วันนี้</span>@endif
            </header>

            @if ($this->daySlots === [])
                <p class="rc-empty">วันนี้ยิมปิด (ไม่มีช่วงเวลาเปิดตามตาราง)</p>
            @else
                <ol class="rc-hours">
                    @foreach ($this->daySlots as $row)
                        @php($r = $row['reservation'])
                        <li class="rc-hour {{ $r ? 'has-booking' : '' }} {{ $r && ! $row['first'] ? 'is-cont' : '' }}">
                            <span class="rc-hour__time">{{ $row['start']->format('H:i') }}</span>
                            @if (! $r)
                                <span class="rc-hour__free">ว่าง</span>
                            @elseif ($row['first'])
                                <a href="{{ $this->recordUrl($r) }}" class="rc-block {{ $statusClass($r) }}">
                                    <span class="rc-block__main">
                                        <strong>{{ $r->payer?->user?->name ?? '-' }}</strong>
                                        <span>{{ $r->timeLabel() }} · {{ $r->hours }} ชม. · {{ $r->participants_count }} คน{{ $r->trainer ? ' · Trainer '.$r->trainer->user?->name : ' · ไม่มี Trainer' }}</span>
                                    </span>
                                    <span class="rc-block__side">
                                        <span class="rc-status {{ $statusClass($r) }}">{{ $r->status->label() }}</span>
                                        <span class="rc-ref">{{ $r->reference }} · ฿{{ number_format((float) $r->amount) }}</span>
                                        @if ($r->status === \App\Enums\ReservationStatus::PendingPayment && $r->hold_expires_at)
                                            <span class="rc-hold">ชำระภายใน {{ $r->hold_expires_at->format('H:i') }}</span>
                                        @endif
                                    </span>
                                </a>
                            @else
                                <span class="rc-hour__cont">│ ต่อเนื่อง</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            @php($closed = $this->dayList->reject(fn ($r) => $r->status->holdsSlots()))
            @if ($closed->isNotEmpty())
                <div class="rc-closed">
                    <p>ยกเลิก / หมดอายุในวันนี้</p>
                    @foreach ($closed as $r)
                        <a href="{{ $this->recordUrl($r) }}" class="rc-closed__item">
                            <span class="rc-status {{ $statusClass($r) }}">{{ $r->status->label() }}</span>
                            <span>{{ $r->timeLabel() }} · {{ $r->payer?->user?->name }} · {{ $r->reference }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
