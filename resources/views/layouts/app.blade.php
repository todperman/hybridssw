<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="relative min-h-screen bg-paper" x-data="appShell">
            <livewire:layout.navigation />

            @if (isset($header))
                <header class="relative z-10 border-b border-line bg-white/60 backdrop-blur-sm">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            {{-- เว้นที่ด้านล่างให้แถบเมนูมือถือ ไม่งั้นเนื้อหาท้ายหน้าถูกบัง --}}
            {{-- ห้ามใส่ z-index ที่นี่ ถ้าใส่ main จะกลายเป็น stacking context
                 แล้วโมดัล z-[80] ข้างในจะถูกกดให้อยู่ใต้แถบเมนู z-50 ตลอด
                 ทำให้ปุ่มท้ายโมดัลบนมือถือถูกแถบล่างบัง --}}
            <main class="relative pb-[76px] sm:pb-0">
                {{ $slot }}
            </main>

            {{-- เตือนก่อนถึงรอบ อยู่ในเลย์เอาต์จึงติดตามผู้ใช้ไปทุกหน้า --}}
            <livewire:upcoming-reminder />

            <x-app-toasts />
            <x-app-dialog />
        </div>

        @stack('scripts')
    </body>
</html>
