{{-- แจ้งผลการทำรายการแบบ toast แทนแถบข้อความค้างอยู่ในหน้า
     ฝั่ง PHP เรียกด้วย $this->dispatch('toast', tone: 'success', title: '...', body: '...') --}}
<div x-data="{
        items: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.items.push({
                id,
                tone: detail.tone ?? 'info',
                title: detail.title ?? '',
                body: detail.body ?? '',
            });
            setTimeout(() => this.remove(id), detail.duration ?? 5000);
        },
        remove(id) { this.items = this.items.filter(i => i.id !== id); },
     }"
     @toast.window="add($event.detail?.[0] ?? $event.detail ?? {})"
     x-cloak
     class="pointer-events-none fixed inset-x-0 top-3 z-[95] flex flex-col items-center gap-2 px-3 sm:inset-x-auto sm:right-5 sm:top-5 sm:items-end sm:px-0">

    <template x-for="item in items" :key="item.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white/95 p-3.5 shadow-lift backdrop-blur-sm"
             :class="{
                 'border-brand-light': item.tone === 'success',
                 'border-red-200': item.tone === 'error',
                 'border-accent': item.tone === 'warning',
                 'border-line': item.tone === 'info',
             }"
             x-transition:enter="transition ease-out-soft duration-300"
             x-transition:enter-start="opacity-0 -translate-y-3 sm:translate-x-6 sm:translate-y-0"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95">

            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full"
                  :class="{
                      'bg-mist text-brand-dark': item.tone === 'success',
                      'bg-red-50 text-red-700': item.tone === 'error',
                      'bg-accent-light text-accent-ink': item.tone === 'warning',
                      'bg-mist text-muted': item.tone === 'info',
                  }">
                @svg('lucide-check', 'h-4 w-4', ['x-show' => 'item.tone === \'success\'', 'stroke-width' => '2.4'])
                @svg('lucide-x', 'h-4 w-4', ['x-show' => 'item.tone === \'error\'', 'stroke-width' => '2.2'])
                @svg('lucide-circle-alert', 'h-4 w-4', ['x-show' => 'item.tone === \'warning\' || item.tone === \'info\'', 'stroke-width' => '2.2'])
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[14px] font-semibold leading-snug text-ink" x-text="item.title"></p>
                <p class="mt-0.5 text-[13px] leading-relaxed text-muted" x-show="item.body" x-text="item.body"></p>
            </div>

            <button type="button" @click="remove(item.id)" aria-label="ปิดข้อความ"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-muted transition hover:bg-mist hover:text-ink">
                @svg('lucide-x', 'h-3.5 w-3.5', ['stroke-width' => '2.2'])
            </button>
        </div>
    </template>
</div>
