<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans text-ink antialiased">
        {{-- ไล่สีพื้นหลังแบบเดียวกับเว็บหลัก --}}
        <div class="relative min-h-screen overflow-hidden bg-paper">
            <div class="pointer-events-none absolute -left-40 -top-40 h-[28rem] w-[28rem] rounded-full bg-brand-light/40 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-52 -right-32 h-[32rem] w-[32rem] rounded-full bg-accent-light/60 blur-3xl"></div>

            <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12">
                <a href="/" wire:navigate class="mb-8">
                    <x-application-logo />
                </a>

                <div class="w-full max-w-md rounded-lg2 border border-line bg-white/85 px-6 py-7 shadow-lift backdrop-blur-sm sm:px-8 sm:max-w-fit">
                    {{ $slot }}
                </div>

                <p class="mt-8 text-xs text-muted">Srisawan Hybrid Workout</p>
            </div>
        </div>
    </body>
</html>
