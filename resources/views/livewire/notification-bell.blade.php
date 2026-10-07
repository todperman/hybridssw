<div x-data="{ open: false }" class="relative" wire:poll.60s @keydown.escape.window="open = false">
    <button type="button" @click="open = ! open" :aria-expanded="open"
            class="glass-icon relative grid h-11 w-11 place-items-center text-ink"
            aria-label="แจ้งเตือน{{ $this->unread ? ' ยังไม่อ่าน '.$this->unread.' รายการ' : '' }}">
        @svg('lucide-bell', 'h-5 w-5', ['stroke-width' => '1.9'])
        @if ($this->unread > 0)
            <span class="absolute -right-0.5 -top-0.5 grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-red-600 px-1 text-[11px] font-bold leading-none text-white">
                {{ $this->unread > 9 ? '9+' : $this->unread }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed inset-x-3 top-[76px] z-[60] overflow-hidden rounded-2xl border border-line bg-white shadow-soft sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:w-96">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="font-display text-[15px] font-bold text-ink">แจ้งเตือน</p>
            @if ($this->unread > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand-deep hover:underline">อ่านทั้งหมดแล้ว</button>
            @endif
        </div>

        <ul class="max-h-[60vh] divide-y divide-line overflow-y-auto">
            @forelse ($this->latest as $n)
                <li wire:key="n-{{ $n->id }}">
                    <button type="button" wire:click="open('{{ $n->id }}')"
                            class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-mist/60 {{ $n->read_at ? '' : 'bg-brand-light/10' }}">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-brand' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-ink">{{ $n->data['title'] ?? 'แจ้งเตือน' }}</span>
                            <span class="mt-0.5 block text-[13px] leading-snug text-muted">{{ $n->data['body'] ?? '' }}</span>
                            <span class="mt-1 block text-[11px] text-muted/80">{{ $n->created_at->locale('th')->diffForHumans() }}</span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-sm text-muted">ยังไม่มีแจ้งเตือน</li>
            @endforelse
        </ul>
    </div>
</div>
