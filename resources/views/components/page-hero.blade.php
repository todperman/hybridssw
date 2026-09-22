@props([
    'eyebrow' => null,
    'title' => '',
    'subtitle' => null,
    // อุปกรณ์ที่ใช้เป็นลายพื้นหลัง เปลี่ยนตามหน้าให้ไม่ซ้ำกัน
    'pattern' => 'kettlebell',
    'patternAlt' => 'dumbbell',
])

{{-- ไม่ใส่ overflow-hidden ที่กล่องนอก เพื่อให้คลื่นล้นลงล่างได้ --}}
<div {{ $attributes->merge(['class' => 'relative bg-[#00334a]']) }}>
    <div class="absolute inset-0 overflow-hidden">
        <div class="hero-surface absolute inset-0"></div>
        <div class="absolute inset-0 grid-lines opacity-[0.28]"></div>
        <div class="orb orb-delay -bottom-24 -left-20 h-64 w-64 bg-brand/25"></div>

        {{-- ชื่อแบรนด์ตัวใหญ่วางเฉียงเป็นลายน้ำ ล้นออกนอกกรอบทั้งซ้ายและขวา
             ตั้งใจให้อ่านไม่ครบคำ ทำหน้าที่เป็นพื้นผิวไม่ใช่ข้อความ
             จึงกัน aria-hidden ไว้และใช้ความทึบต่ำมากไม่ให้แย่งหัวข้อจริง --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span class="hero-wordmark">Hybrid<br>Workout</span>
        </div>

        {{-- ลายอุปกรณ์จางๆ ใช้ชุดเดียวกับแผงแบรนด์หน้า login
             วางไว้ที่ component นี้ที่เดียว หัวหน้าทุกหน้าในแอปจึงได้เหมือนกันหมด --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden text-white/[0.07]" aria-hidden="true">
            <x-station-icon :name="$pattern" class="absolute -right-6 -top-10 h-52 w-52 rotate-12" stroke="1" />
            <x-station-icon :name="$patternAlt" class="absolute right-36 -bottom-16 hidden h-44 w-44 -rotate-6 sm:block" stroke="1" />
        </div>

        {{-- แสงนวลบาง ๆ ไล่ลงมาจากขอบบน ทำให้รอยต่อกับเมนูดูเป็นผิวที่ถูกแสง
             ไม่ใช่รอยตัดระหว่างสองสี --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-white/[0.14] to-transparent"></div>

        <div class="hero-grain pointer-events-none absolute inset-0"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                @if ($eyebrow)
                    <span class="chip border border-white/25 bg-white/15 text-white backdrop-blur-sm">
                        {{ $eyebrow }}
                    </span>
                @endif

                <h1 class="mt-3 font-display text-3xl font-extrabold heading-th text-paper sm:text-4xl">
                    {{ $title }}
                </h1>

                @if ($subtitle)
                    <p class="mt-1.5 text-[14px] text-white/80">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </div>

        @isset($stats)
            {{ $stats }}
        @endisset

        {{ $slot ?? '' }}
    </div>

    <x-wave-divider />
</div>
