@php
    // ค่าตั้งต้นของแผงแบรนด์ แต่ละหน้าส่งทับได้ผ่าน ->layout('layouts.auth', [...])
    $eyebrow ??= 'Srisawan Hybrid Workout';
    $heading ??= 'Train beyond limits.';
    $headingAccent ??= 'Evolve.';
    $lead ??= 'ระบบจองชั่วโมงการเล่นสำหรับเทรนเนอร์และทีมของคุณ';
    $points ??= [];
    $stats ??= [];
    $altHref ??= null;
    $altLabel ??= null;
    $altCta ??= null;
    $wide ??= false;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="lg:grid lg:h-screen lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">

            {{-- แผงแบรนด์ ซ่อนบนจอเล็กเพื่อให้ฟอร์มได้พื้นที่เต็ม --}}
            <aside class="relative hidden overflow-hidden bg-[#00334a] lg:flex lg:flex-col">
                <div class="hero-surface absolute inset-0"></div>
                <div class="absolute inset-0 grid-lines opacity-[0.3]"></div>

                <div class="orb orb-slow -left-28 -top-24 h-[30rem] w-[30rem] bg-brand/30"></div>
                <div class="orb orb-delay -bottom-36 -right-24 h-[34rem] w-[34rem] bg-accent/20"></div>
                <div class="orb left-1/3 top-1/2 h-72 w-72 bg-brand-light/20"></div>

                {{-- ลายอุปกรณ์จางๆ ให้แผงไม่ว่างเปล่า แต่ไม่แย่งความสนใจจากข้อความ --}}
                <div class="pointer-events-none absolute inset-0 overflow-hidden text-white/[0.055]" aria-hidden="true">
                    <x-station-icon name="ski" class="absolute -left-10 top-16 h-56 w-56 -rotate-12" stroke="1" />
                    <x-station-icon name="kettlebell" class="absolute right-8 top-1/3 h-40 w-40 rotate-6" stroke="1" />
                    <x-station-icon name="sled" class="absolute -bottom-8 left-1/4 h-64 w-64 -rotate-6" stroke="1" />
                </div>

                <div class="hero-grain pointer-events-none absolute inset-0"></div>

                {{-- เส้นแบ่งแบบคลื่น วาดยาวเกินกรอบทั้งบนและล่าง
                     เลื่อนขึ้นครบ 1 รอบ (400 หน่วย) แล้วภาพต่อกันสนิท จึงวนไม่มีรอยต่อ --}}
                {{-- ล้นไปทางขวา 1px แล้วให้ overflow-hidden ของ aside ตัดทิ้ง
                     กันเส้นขนแมวที่เกิดจากขอบแบ่งคอลัมน์ตกลงบนตำแหน่งเศษพิกเซล --}}
                <div class="pointer-events-none absolute inset-y-0 -right-px w-[111px] xl:w-[141px]" aria-hidden="true">
                    <svg class="h-full w-full" viewBox="0 0 120 1000" preserveAspectRatio="none">
                        <path class="wave-line-2" d="M26,-600 C41,-528 41,-472 26,-400 C11,-328 11,-272 26,-200 C41,-128 41,-72 26,0 C11,72 11,128 26,200 C41,272 41,328 26,400 C11,472 11,528 26,600 C41,672 41,728 26,800 C11,872 11,928 26,1000 C41,1072 41,1128 26,1200 C11,1272 11,1328 26,1400 C41,1472 41,1528 26,1600 C11,1672 11,1728 26,1800" fill="none"
                              stroke="rgba(255,215,88,0.16)" stroke-width="1.5"/>

                        <path class="wave-line-1" d="M46,-600 C67,-528 67,-472 46,-400 C25,-328 25,-272 46,-200 C67,-128 67,-72 46,0 C25,72 25,128 46,200 C67,272 67,328 46,400 C25,472 25,528 46,600 C67,672 67,728 46,800 C25,872 25,928 46,1000 C67,1072 67,1128 46,1200 C25,1272 25,1328 46,1400 C67,1472 67,1528 46,1600 C25,1672 25,1728 46,1800" fill="none"
                              stroke="rgba(43,187,215,0.22)" stroke-width="1.5"/>

                        <path class="wave-fill" d="M70,-600 C97,-528 97,-472 70,-400 C43,-328 43,-272 70,-200 C97,-128 97,-72 70,0 C43,72 43,128 70,200 C97,272 97,328 70,400 C43,472 43,528 70,600 C97,672 97,728 70,800 C43,872 43,928 70,1000 C97,1072 97,1128 70,1200 C43,1272 43,1328 70,1400 C97,1472 97,1528 70,1600 C43,1672 43,1728 70,1800 L120,1800 L120,-600 Z" fill="#f3f6f8" stroke="none"/>

                        {{-- ไฮไลต์ขอบวาดแยกเป็น path เปิด ไม่งั้น stroke จะไปวาดทับขอบตรงของ path ที่ปิดสนิท --}}
                        <path class="wave-edge" d="M70,-600 C97,-528 97,-472 70,-400 C43,-328 43,-272 70,-200 C97,-128 97,-72 70,0 C43,72 43,128 70,200 C97,272 97,328 70,400 C43,472 43,528 70,600 C97,672 97,728 70,800 C43,872 43,928 70,1000 C97,1072 97,1128 70,1200 C43,1272 43,1328 70,1400 C97,1472 97,1528 70,1600 C43,1672 43,1728 70,1800" fill="none"
                              stroke="rgba(43,187,215,0.30)" stroke-width="1"/>
                    </svg>
                </div>

                <div class="relative flex h-full flex-col justify-between p-12 xl:p-16">
                    <a href="{{ route('home') }}" wire:navigate class="fade-up w-fit">
                        <x-application-logo dark />
                    </a>

                    <div class="max-w-lg">
                        <span class="fade-up d-1 chip border border-white/15 bg-white/10 text-brand-light backdrop-blur-sm">
                            <span class="mr-2 h-1.5 w-1.5 rounded-full bg-brand-light"></span>
                            {{ $eyebrow }}
                        </span>

                        <h1 class="fade-up d-2 mt-6 font-display text-5xl font-extrabold heading-th text-paper xl:text-[3.5rem]">
                            {{ $heading }}<br>
                            <span class="text-brand-light">{{ $headingAccent }}</span>
                        </h1>

                        <p class="fade-up d-3 mt-5 text-[17px] leading-relaxed text-brand-light/75">
                            {{ $lead }}
                        </p>

                        @if ($points)
                            <ul class="mt-10 space-y-4">
                                @foreach ($points as $i => $point)
                                    <li class="fade-up d-{{ min($i + 4, 6) }} flex items-start gap-3.5">
                                        <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full border border-white/15 bg-white/10 backdrop-blur-sm">
                                            <svg class="h-3.5 w-3.5 text-brand-light" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                        <span class="text-[15px] leading-relaxed text-paper/85">{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="fade-up d-6">
                        @if ($stats)
                            <dl class="flex gap-10 border-t border-white/10 pt-7">
                                @foreach ($stats as $label => $value)
                                    <div>
                                        <dt class="text-xs text-brand-light/60">{{ $label }}</dt>
                                        <dd class="mt-1 font-display text-2xl font-bold text-paper">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <p class="text-xs text-brand-light/50">Srisawan Hybrid Workout</p>
                        @endif
                    </div>
                </div>
            </aside>

            {{-- ฝั่งฟอร์ม --}}
            {{-- overflow-x-hidden จำเป็น ไม่งั้นก้อนแสงตกแต่งด้านล่างจะดันให้หน้าเลื่อนแนวนอนได้ --}}
            <main class="relative flex min-h-screen flex-col overflow-x-hidden bg-paper lg:h-screen lg:min-h-0 lg:overflow-y-auto">

                {{-- แสงตกแต่งวางไว้ครึ่งล่าง ห่างจากคลื่นพอสมควร
                     ของเดิมเป็นวงเบลอ 64px ที่ก้นวงอยู่พอดีขอบแถบ ส่วนที่เบลอเลยล้นลงมา
                     ใต้คลื่นกลายเป็นคราบขาว ทำให้รอยต่อดูไม่เนียน --}}
                <div class="pointer-events-none absolute -right-24 bottom-24 h-80 w-80 rounded-full bg-brand-light/15 blur-3xl lg:hidden"></div>

                {{-- จอเล็กใช้หัวที่อยู่บนแถบแบรนด์แทน --}}
                <header class="relative hidden items-center justify-between px-6 py-6 sm:px-10 lg:flex">
                    <a href="{{ route('home') }}" wire:navigate class="lg:invisible">
                        <x-application-logo />
                    </a>

                    @if ($altHref)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="hidden text-muted sm:inline">{{ $altLabel }}</span>
                            <a href="{{ $altHref }}" wire:navigate class="btn-ghost">{{ $altCta }}</a>
                        </div>
                    @endif
                </header>

                {{-- แถบแบรนด์สำหรับจอเล็ก กินเต็มความกว้างจอและไม่มีมุมโค้ง
                     ถ้าใส่มุมโค้งหรือเว้นขอบ จะเห็นเส้นขอบการ์ดพาดใต้คลื่น --}}
                <div class="relative bg-[#00334a] lg:hidden">
                    {{-- ชั้นตกแต่งถูกคลิปแยกต่างหาก ตัวแถบจึงไม่ต้องมี overflow-hidden
                         ทำให้คลื่นด้านล่างล้นออกนอกขอบแถบได้โดยไม่ถูกตัด --}}
                    <div class="absolute inset-0 overflow-hidden">
                        <div class="hero-surface absolute inset-0"></div>
                        <div class="absolute inset-0 grid-lines opacity-[0.28]"></div>
                        <div class="orb orb-delay -bottom-24 -left-12 h-52 w-52 bg-brand/25"></div>
                        <div class="hero-grain pointer-events-none absolute inset-0"></div>
                    </div>

                    <div class="relative">
                        <div class="mx-auto flex max-w-2xl items-center justify-between px-5 pt-5 sm:px-8">
                            <a href="{{ route('home') }}" wire:navigate>
                                <x-application-logo dark />
                            </a>

                            @if ($altHref)
                                <a href="{{ $altHref }}" wire:navigate
                                   class="inline-flex min-h-[44px] items-center rounded-full border border-white/20 bg-white/10 px-5 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                                    {{ $altCta }}
                                </a>
                            @endif
                        </div>

                        <div class="mx-auto max-w-2xl px-5 pb-16 pt-6 sm:px-8">
                            <span class="chip border border-white/25 bg-white/15 text-white backdrop-blur-sm">
                                <span class="mr-2 h-1.5 w-1.5 rounded-full bg-brand-light"></span>
                                {{ $eyebrow }}
                            </span>

                            <h1 class="mt-3.5 font-display text-[28px] font-extrabold heading-th text-paper">
                                {{ $heading }}
                                <span class="text-brand-light">{{ $headingAccent }}</span>
                            </h1>

                            <p class="mt-2.5 text-[14px] leading-relaxed text-white/80">{{ $lead }}</p>
                        </div>
                    </div>

                    <x-wave-divider />

                </div>


                {{-- จอเล็กจัดชิดบน เพราะมีแถบแบรนด์อยู่เหนือฟอร์มแล้ว ส่วนจอใหญ่จัดกึ่งกลาง --}}
                <div class="relative flex flex-1 items-start justify-center px-6 pb-14 pt-7 sm:px-10 lg:items-center lg:pt-2">
                    <div class="w-full {{ $wide ? 'max-w-2xl' : 'max-w-md' }}">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
