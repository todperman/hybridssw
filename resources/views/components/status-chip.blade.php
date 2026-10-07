{{-- ป้ายสถานะของ enum ที่มี label() กับ color() แบบ Filament ใช้กับหน้าฝั่งผู้ใช้ --}}
@props(['status'])

@php($tone = match ($status->color()) {
    'success' => 'bg-emerald-100 text-emerald-800',
    'warning' => 'bg-accent-light text-accent-ink',
    'danger' => 'bg-red-100 text-red-700',
    'info' => 'bg-brand-light/40 text-brand-dark',
    default => 'bg-line/60 text-muted',
})

<span {{ $attributes->merge(['class' => "chip $tone"]) }}>{{ $status->label() }}</span>
