@props(['tone' => 'info'])

@php($styles = ['info' => ['border-brand-deep/20 bg-brand-light/10 text-brand-dark', 'lightbulb'], 'warn' => ['border-amber-300/60 bg-amber-50 text-amber-900', 'triangle-alert'], 'ok' => ['border-emerald-300/60 bg-emerald-50 text-emerald-900', 'circle-check']][$tone])

{{-- กล่องเน้นสั้น ๆ ข้อควรรู้ / ข้อควรระวัง --}}
<div class="flex items-start gap-2.5 rounded-2xl border px-3.5 py-3 text-[13.5px] leading-relaxed {{ $styles[0] }}">
    <x-dynamic-component :component="'lucide-'.$styles[1]" class="mt-0.5 h-4 w-4 shrink-0" stroke-width="2" />
    <div>{{ $slot }}</div>
</div>
