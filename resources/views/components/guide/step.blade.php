@props(['n', 'title', 'icon' => null])

{{-- ขั้นตอนหนึ่งข้อในคู่มือ: เลขลำดับ หัวข้อ แล้วรายละเอียดใน slot --}}
<li class="relative flex gap-4 py-4 first:pt-0 last:pb-0">
    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-dark font-display text-[15px] font-bold text-white shadow-soft">{{ $n }}</span>
    <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 font-display text-[15px] font-bold text-ink">
            @if ($icon)
                <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4 text-brand-deep" stroke-width="2" />
            @endif
            {{ $title }}
        </p>
        <div class="mt-1 space-y-1.5 text-[14px] leading-relaxed text-muted">{{ $slot }}</div>
    </div>
</li>
