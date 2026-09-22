@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'w-full rounded-xl border-line bg-white/80 text-sm text-ink shadow-sm transition focus:border-brand-deep focus:ring-brand-deep/30 disabled:bg-mist disabled:text-muted',
]) }}>
