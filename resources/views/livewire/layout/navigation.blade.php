<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    /** เมนูขึ้นตามบทบาทและตามว่ามีโปรไฟล์จริงหรือยัง */
    public function links(): array
    {
        $user = auth()->user();
        $links = [];

        // icon ใช้กับแถบเมนูล่างบนมือถือ ชื่อตรงกับชุด Lucide
        if ($user?->trainer) {
            // ยังไม่อนุมัติก็เข้าหน้าพวกนี้ไม่ได้ ขึ้นเมนูไว้มีแต่จะกดแล้วเด้งกลับ
            if ($user->trainer->isApproved()) {
                $links[] = ['route' => 'trainer.schedule', 'label' => 'ตารางจอง', 'icon' => 'calendar-days'];
                $links[] = ['route' => 'trainer.team', 'label' => 'ทีมของฉัน', 'icon' => 'users'];
                $links[] = ['route' => 'trainer.insights', 'label' => 'สถิติ', 'icon' => 'chart-column'];
            } else {
                $links[] = ['route' => 'trainer.pending', 'label' => 'สถานะใบสมัคร', 'icon' => 'clock'];
            }
        }

        if ($user?->member) {
            $links[] = ['route' => 'member.bookings', 'label' => 'คิวของฉัน', 'icon' => 'ticket'];
        }

        if ($user?->role?->canAccessAdminPanel()) {
            $links[] = ['url' => url('/admin'), 'label' => 'ตั้งค่าระบบ', 'icon' => 'settings'];
        }

        return $links;
    }
}; ?>

{{-- sticky ต้องอยู่ที่ root ไม่ใช่ที่ <nav> ข้างใน
     เพราะ element ที่ sticky จะเลื่อนได้แค่ในกรอบของพ่อแม่ตัวเอง
     ถ้ากรอบสูงเท่าแถบพอดี แถบก็จะไหลหายไปกับหน้าทันที --}}
<div x-data="{ account: false }" class="sticky top-0 z-50">
<nav data-app-nav class="border-b border-line bg-white/85 backdrop-blur-md">
    {{-- ไล่สีเหลืองจางจากขอบบนลงล่างแล้วจางหายไป วางเป็นชั้นหลังเนื้อหา
         ใช้ opacity ต่ำเพื่อให้ยังอ่านตัวอักษรได้สบาย --}}
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-accent/30 via-accent-light/12 to-transparent"></div>

    {{-- ไล่โทนแบรนด์บาง ๆ ที่ขอบล่าง ให้รอยต่อกับหัวเรื่องสีเข้มไม่เป็นเส้นตัดคม
         ถ้าปล่อยให้ครีมชนน้ำเงินเข้มตรง ๆ ตาจะเห็นเป็นเส้นแบ่งทันที --}}
    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-6 bg-gradient-to-b from-transparent via-brand-light/10 to-brand-deep/25"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-[72px] justify-between">

            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" wire:navigate>
                    <x-application-logo />
                </a>

                <div class="hidden gap-1 sm:flex">
                    @foreach ($this->links() as $link)
                        @php($href = $link['url'] ?? route($link['route']))
                        @php($active = isset($link['route']) && request()->routeIs($link['route']))

                        <a href="{{ $href }}" @if (isset($link['route'])) wire:navigate @endif
                           class="rounded-full px-4 py-2 text-sm font-medium transition
                                  {{ $active ? 'bg-brand-dark text-white shadow-soft' : 'text-muted hover:bg-mist hover:text-ink' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- เมนูผู้ใช้ --}}
            <div class="hidden items-center sm:flex">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="glass-toggle min-h-[44px] gap-2 px-4 text-sm text-ink">
                            <x-avatar :user="auth()->user()" size="h-7 w-7" text="text-[11px]" />
                            <span x-data="{}" x-text="'{{ auth()->user()?->name }}'"></span>
                            @svg('lucide-chevron-down', 'h-4 w-4 text-muted')
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-line px-4 py-3">
                            <p class="text-xs text-muted">{{ auth()->user()?->role?->label() }}</p>
                            <p class="truncate text-sm text-ink">{{ auth()->user()?->email }}</p>
                        </div>

                        <x-dropdown-link :href="route('profile')" wire:navigate>โปรไฟล์</x-dropdown-link>

                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>ออกจากระบบ</x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

        </div>
    </div>

</nav>

{{-- มือถือใช้แถบเมนูล่างแทนเมนูแบบพับ กดถึงได้ด้วยนิ้วโป้งและเห็นว่าอยู่หน้าไหนตลอด --}}
<nav class="bottom-nav sm:hidden" aria-label="เมนูหลัก">
    <div class="bottom-nav__bar">
    @foreach ($this->links() as $link)
        @php($href = $link['url'] ?? route($link['route']))
        @php($active = isset($link['route']) && request()->routeIs($link['route']))

        <a href="{{ $href }}" @if (isset($link['route'])) wire:navigate @endif
           class="bottom-nav__item {{ $active ? 'is-active' : '' }}"
           @if ($active) aria-current="page" @endif>
            <span class="bottom-nav__icon">
                <x-dynamic-component :component="'lucide-'.($link['icon'] ?? 'circle')" class="h-5 w-5" stroke-width="1.9" />
            </span>
            <span class="bottom-nav__label">{{ $link['label'] }}</span>
        </a>
    @endforeach

    <button type="button" @click="account = true"
            class="bottom-nav__item {{ request()->routeIs('profile') ? 'is-active' : '' }}">
        <span class="bottom-nav__icon">
            @svg('lucide-user-round', 'h-5 w-5', ['stroke-width' => '1.9'])
        </span>
        <span class="bottom-nav__label">บัญชี</span>
    </button>
    </div>
</nav>

{{-- แผ่นบัญชีผู้ใช้ เปิดจากแถบล่าง --}}
<div x-show="account" x-cloak class="fixed inset-0 z-[70] sm:hidden" @keydown.escape.window="account = false">
    <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" @click="account = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>

    <div class="glass-panel absolute inset-x-0 bottom-0 rounded-t-[28px] px-5 pb-[calc(1.25rem+env(safe-area-inset-bottom,0px))] pt-2.5"
         x-transition:enter="transition ease-out-soft duration-300"
         x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">

        <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-ink/15"></div>

        <div class="flex items-center gap-3">
            <x-avatar :user="auth()->user()" size="h-12 w-12" text="text-base" class="ring-2 ring-white/80" />
            <div class="min-w-0">
                <p class="truncate font-display text-[16px] font-bold text-ink">{{ auth()->user()?->displayName() }}</p>
                <p class="truncate text-[12px] text-muted">{{ auth()->user()?->email }}</p>
                <p class="text-[11px] text-muted/70">{{ auth()->user()?->role?->label() }}</p>
            </div>
        </div>

        <div class="mt-4 space-y-1.5">
            <a href="{{ route('profile') }}" wire:navigate @click="account = false"
               class="glass-row w-full gap-3 px-4 py-3 text-sm text-ink">
                @svg('lucide-user-round', 'h-4 w-4 text-brand-deep', ['stroke-width' => '1.9'])
                โปรไฟล์
            </a>

            <button wire:click="logout" class="glass-row w-full gap-3 px-4 py-3 text-start text-sm text-red-700">
                @svg('lucide-log-out', 'h-4 w-4', ['stroke-width' => '1.9'])
                ออกจากระบบ
            </button>
        </div>
    </div>
</div>
</div>
