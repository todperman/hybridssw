@props(['dark' => false])

<span {{ $attributes->merge(['class' => 'inline-flex min-h-[44px] items-center gap-2.5']) }}>
    {{-- ไฟล์ต้นฉบับมีพื้นหลังโปร่งใสอยู่แล้ว วางบนพื้นสีไหนก็ได้
         ใช้ไฟล์ย่อขนาดเพื่อไม่ต้องโหลดภาพ 403px มาแสดงที่ 36px --}}
    <img src="{{ asset('img/logo@180.png') }}" alt="Srisawan Hybrid Workout"
         width="180" height="183" class="h-9 w-auto shrink-0" fetchpriority="high">

    <span class="font-display text-[13px] font-extrabold uppercase leading-[1.05] tracking-tight {{ $dark ? 'text-paper' : 'text-ink' }}">
        Hybrid<br>Workout
    </span>
</span>
