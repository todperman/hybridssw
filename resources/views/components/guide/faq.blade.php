@props(['q'])

{{-- คำถาม-คำตอบ กดเปิดอ่านทีละข้อ --}}
<div x-data="{ open: false }" class="border-b border-line last:border-0">
    <button type="button" @click="open = !open" :aria-expanded="open"
            class="flex w-full items-start justify-between gap-3 py-3.5 text-start">
        <span class="font-medium text-ink">{{ $q }}</span>
        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-mist text-muted transition" :class="open ? 'rotate-180 bg-brand-light/30 text-brand-dark' : ''">
            @svg('lucide-chevron-down', 'h-4 w-4', ['stroke-width' => '2.2'])
        </span>
    </button>
    <div x-show="open" x-collapse x-cloak class="pb-4 text-[14px] leading-relaxed text-muted">{{ $slot }}</div>
</div>
