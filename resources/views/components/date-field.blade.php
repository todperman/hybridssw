@props([
    // ชื่อ property ของ Livewire ที่จะผูกค่าไว้
    'model',
    'placeholder' => 'เลือกวันที่',
    'min' => null,
    'max' => null,
])

{{-- ปฏิทินของแอปเอง ไม่ใช้ <input type="date">
     เพราะป๊อปอัปของ input เป็นของเบราว์เซอร์ แต่งด้วย CSS ไม่ได้
     และหน้าตาต่างกันไปทุกระบบปฏิบัติการ --}}
<div x-data="datePicker('{{ $model }}', @js(array_filter(['placeholder' => $placeholder, 'min' => $min, 'max' => $max])))"
     @keydown.escape.window="open = false"
     @click.outside="open = false"
     class="relative">

    <button type="button" @click="toggle()"
            :aria-expanded="open" aria-haspopup="dialog"
            {{ $attributes->merge(['class' => 'glass-input inline-flex min-h-[38px] items-center gap-2 px-3 text-[13px] text-ink transition hover:border-brand/40 sm:min-h-[44px]']) }}>
        @svg('lucide-calendar-days', 'h-4 w-4 shrink-0 text-brand-deep', ['stroke-width' => '1.9'])
        <span x-text="label" class="truncate"></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         role="dialog" aria-label="เลือกวันที่"
         class="ss-cal">

        <div class="mb-2 flex items-center justify-between gap-2">
            <button type="button" @click="shiftMonth(-1)" aria-label="เดือนก่อนหน้า" class="ss-cal__nav">
                @svg('lucide-chevron-left', 'h-4 w-4', ['stroke-width' => '2.2'])
            </button>

            <p class="font-display text-[14px] font-bold text-ink" x-text="title"></p>

            <button type="button" @click="shiftMonth(1)" aria-label="เดือนถัดไป" class="ss-cal__nav">
                @svg('lucide-chevron-right', 'h-4 w-4', ['stroke-width' => '2.2'])
            </button>
        </div>

        <div class="grid grid-cols-7 gap-0.5">
            <template x-for="d in weekdays" :key="d">
                <span class="grid h-7 place-items-center text-[10px] font-semibold text-muted" x-text="d"></span>
            </template>

            <template x-for="(cell, i) in days" :key="cell ? cell.key : 'x' + i">
                <div>
                    <template x-if="cell">
                        <button type="button" @click="pick(cell)" :disabled="cell.isDisabled"
                                class="ss-cal__day"
                                :class="{
                                    'is-selected': cell.isSelected,
                                    'is-today': cell.isToday && ! cell.isSelected,
                                    'is-past': cell.isPast && ! cell.isSelected,
                                }"
                                x-text="cell.n"></button>
                    </template>
                </div>
            </template>
        </div>

        <div class="mt-2 flex justify-end border-t border-line/70 pt-2">
            <button type="button" @click="goToday()" class="ss-cal__today">วันนี้</button>
        </div>
    </div>
</div>
