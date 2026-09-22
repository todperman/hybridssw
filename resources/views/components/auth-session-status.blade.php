@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-brand-light bg-mist px-4 py-3 text-sm font-medium text-brand-dark']) }}>
        {{ $status }}
    </div>
@endif
