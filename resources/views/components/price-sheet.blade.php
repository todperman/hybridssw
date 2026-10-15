@props([
    'branch',
    // จำนวนคน แบบ และชั่วโมงที่เลือกอยู่ (ถ้ามี) ใช้ไฮไลต์แถวและสรุปยอด
    'people' => null,
    'withTrainer' => null,
    'hours' => 1,
    'label' => 'ดูราคา',
])

@php
    $table = $branch->priceTable();
    $maxPeople = max((int) $branch->max_trainees, 1);
    $modes = [
        'with_trainer' => ['title' => 'มี Trainer', 'hint' => 'Trainer ดูแลตลอดชั่วโมง', 'icon' => 'users'],
        'without_trainer' => ['title' => 'ไม่มี Trainer', 'hint' => 'เฉพาะสมาชิกที่ได้รับสิทธิ์เข้าใช้เอง', 'icon' => 'user-round'],
    ];
    $rows = [];
    foreach ($modes as $mode => $meta) {
        $isWith = $mode === 'with_trainer';
        $tiers = $table->table[$mode]['tiers'] ?? [];
        $extra = $table->table[$mode]['extra_per_person'] ?? 0;
        $shown = min($maxPeople, max(count($tiers), 1));
        $rows[$mode] = [
            'rows' => collect(range(1, $shown))->map(fn ($n) => [
                'people' => $n,
                'per_hour' => $table->perHour($n, $isWith),
                'per_person' => round($table->perHour($n, $isWith) / $n),
            ])->all(),
            'extra' => $extra,
            'extra_from' => $shown + 1,
            'more' => $maxPeople > $shown,
        ];
    }
    $initial = $withTrainer === null ? 'with_trainer' : ($withTrainer ? 'with_trainer' : 'without_trainer');
@endphp

{{-- ปุ่มเปิดกับกล่องราคา: มือถือเลื่อนขึ้นจากล่าง จอใหญ่เป็นโมดัลกลางจอ
     ตัวเลขทั้งหมดคำนวณฝั่งเซิร์ฟเวอร์จากตารางราคาของสาขา JS แค่สลับแท็บกับเปิดปิด --}}
<div x-data="{ open: false, mode: @js($initial) }"
     x-on:keydown.escape.window="open = false"
     x-on:price-sheet-open.window="open = true"
     {{ $attributes->only('class') }}>

    <button type="button" x-on:click="open = true"
            {{ $attributes->except('class')->merge(['class' => 'inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20']) }}>
        @svg('lucide-receipt', 'h-4 w-4', ['stroke-width' => '2'])
        {{ $label }}
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="modal-scrim z-[80]" role="dialog" aria-modal="true" aria-label="ตารางราคา"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="absolute inset-0" x-on:click="open = false"></div>

            <div class="modal-panel glass-panel relative"
                 x-transition:enter="transition ease-out-soft duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95" x-transition:enter-end="translate-y-0 sm:scale-100">

                <div class="glass-head flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-brand-deep">{{ $branch->name }}</p>
                        <h2 class="font-display text-[20px] font-bold leading-tight text-ink">ตารางราคา</h2>
                        <p class="mt-0.5 text-[12px] text-muted">บาทต่อชั่วโมง รวมทั้งกลุ่ม ยิ่งมากันเยอะยิ่งถูกลงต่อคน</p>
                    </div>
                    <button type="button" x-on:click="open = false" aria-label="ปิด"
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-muted transition hover:bg-white/70 hover:text-ink">
                        @svg('lucide-x', 'h-4 w-4', ['stroke-width' => '2.2'])
                    </button>
                </div>

                <div class="px-4 pt-4 sm:px-5">
                    <div class="grid grid-cols-2 gap-1 rounded-full border border-line/70 bg-white/60 p-1" role="tablist">
                        @foreach ($modes as $mode => $meta)
                            <button type="button" role="tab" x-on:click="mode = @js($mode)" :aria-selected="mode === @js($mode)"
                                    :class="mode === @js($mode) ? 'bg-brand-dark text-white shadow-soft' : 'text-ink hover:bg-mist'"
                                    class="flex min-h-[40px] items-center justify-center gap-1.5 rounded-full px-3 text-sm font-medium transition">
                                @svg('lucide-'.$meta['icon'], 'h-4 w-4', ['stroke-width' => '2'])
                                {{ $meta['title'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-4 pt-3 sm:px-5">
                    @foreach ($modes as $mode => $meta)
                        @php($data = $rows[$mode])
                        @php($isWith = $mode === 'with_trainer')
                        <div x-show="mode === @js($mode)" x-cloak>
                            <p class="mb-3 text-[13px] text-muted">{{ $meta['hint'] }}</p>

                            <ul class="divide-y divide-line/70 overflow-hidden rounded-2xl border border-line/70 bg-white/70">
                                @foreach ($data['rows'] as $row)
                                    @php($active = $people !== null && $people === $row['people'] && ($withTrainer === null || $withTrainer === $isWith))
                                    <li class="flex items-center gap-3 px-4 py-3 {{ $active ? 'bg-brand-light/25' : '' }}" wire:key="price-{{ $mode }}-{{ $row['people'] }}">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full font-display text-[15px] font-bold {{ $active ? 'bg-brand-dark text-white' : 'bg-mist text-ink' }}">
                                            {{ $row['people'] }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-ink">{{ $row['people'] }} คน</span>
                                            <span class="block text-[12px] text-muted">คนละ ฿{{ number_format($row['per_person'], 0) }}/ชม.</span>
                                        </span>
                                        <span class="text-right">
                                            <span class="block font-display text-[17px] font-bold leading-none text-ink">฿{{ number_format($row['per_hour'], 0) }}</span>
                                            <span class="block text-[11px] text-muted">รวม/ชม.</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($data['more'] && $data['extra'] > 0)
                                <p class="mt-3 flex items-start gap-2 text-[13px] text-muted">
                                    @svg('lucide-plus-circle', 'mt-0.5 h-4 w-4 shrink-0 text-brand-deep', ['stroke-width' => '2'])
                                    คนที่ {{ $data['extra_from'] }} ขึ้นไป เพิ่มคนละ ฿{{ number_format($data['extra'], 0) }}/ชม. (สูงสุด {{ $maxPeople }} คน)
                                </p>
                            @endif
                        </div>
                    @endforeach

                    @if ($people !== null && $withTrainer !== null)
                        @php($q = $table->quote($people, $withTrainer, $hours))
                        <div class="mt-4 rounded-2xl border border-brand/30 bg-brand-light/20 px-4 py-3">
                            <p class="text-[12px] text-muted">ที่เลือกอยู่ · {{ $q->label() }}</p>
                            <p class="font-display text-2xl font-bold text-ink">฿{{ number_format($q->amount, 0) }}</p>
                        </div>
                    @endif

                    <p class="mt-4 text-[12px] leading-relaxed text-muted">
                        จองได้ครั้งละ 1–{{ $branch->max_booking_hours }} ชั่วโมงต่อเนื่อง · ผู้ชำระเงินจ่ายเต็มจำนวนคนเดียว · ราคาเป็นของกลุ่ม ไม่ใช่ต่อคน
                    </p>
                    @php($benefitTypes = \App\Models\MemberType::active()->orderBy('sort_order')->get())
                    @if ($benefitTypes->isNotEmpty())
                        <p class="mt-2 flex items-start gap-2 text-[12px] leading-relaxed text-muted">
                            @svg('lucide-badge-check', 'mt-0.5 h-4 w-4 shrink-0 text-emerald-600', ['stroke-width' => '2'])
                            {{ $benefitTypes->pluck('name')->join(' / ') }} ที่ได้รับสิทธิ์แล้ว ส่วนของตัวเองฟรี{{ $benefitTypes->contains(fn ($t) => ! $t->isUnlimited()) ? ' ตามโควตาต่อเดือน' : '' }} เพื่อนที่มาด้วยจ่ายตามส่วนของตัวเอง
                        </p>
                    @endif
                </div>

                <div class="glass-foot">
                    <button type="button" x-on:click="open = false" class="btn-grad w-full py-3 text-[15px] font-semibold">เข้าใจแล้ว</button>
                </div>
            </div>
        </div>
    </template>
</div>
