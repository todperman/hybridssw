@props([
    'user' => null,
    'size' => 'h-10 w-10',
    'text' => 'text-sm',
])

@php
    $url = $user?->avatarUrl();

    // ไม่มีรูปก็ใช้อักษรย่อ เอาชื่อ-นามสกุลอย่างละตัว ถ้ามีแค่ชื่อเดียวก็ตัวเดียว
    $source = trim((string) ($user?->nickname ?: $user?->name));
    $parts = preg_split('/\s+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $initials = $parts === []
        ? '?'
        : mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '');

    // สีประจำตัวคงที่ต่อคน สุ่มจากชื่อด้วย crc32 คนเดิมจึงได้สีเดิมเสมอทุกหน้า
    // ใช้พาเลตต์ชุดเดียวกับสีกลุ่ม สีทุกตัวจึงอยู่ในระบบของแบรนด์ ไม่หลุดโทน
    $palette = array_values(\App\Support\GroupPalette::COLORS);
    $c = $palette[$user ? crc32($source ?: (string) $user->id) % count($palette) : 0];
@endphp

<span {{ $attributes->merge([
    'class' => "ss-avatar relative inline-grid $size shrink-0 place-items-center overflow-hidden rounded-full $text",
]) }}
    @unless ($url)
        style="--a-soft: {{ $c['soft'] }}; --a-base: {{ $c['base'] }}; --a-ink: {{ $c['ink'] }}"
    @endunless>

    @if ($url)
        <img src="{{ $url }}" alt="{{ $user->name }}" loading="lazy"
             class="h-full w-full object-cover">
    @else
        <span class="ss-avatar__initials">{{ $initials }}</span>
    @endif
</span>
