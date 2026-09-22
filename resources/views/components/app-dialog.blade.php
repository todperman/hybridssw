{{-- กล่องยืนยันกลางของแอป ใช้แทน confirm() ของเบราว์เซอร์
     เรียกจากปุ่มไหนก็ได้ด้วย $store.confirm.ask({ ... , action: () => $wire.something() }) --}}
<div x-data x-cloak
     @keydown.escape.window="$store.confirm.open && $store.confirm.dismiss()">

    <template x-if="$store.confirm.open">
        <div class="modal-scrim z-[90] bg-transparent backdrop-blur-none"
             role="dialog" aria-modal="true" :aria-label="$store.confirm.title">

            <div class="absolute inset-0 bg-ink/45 backdrop-blur-sm"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 @click="$store.confirm.dismiss()"></div>

            <div class="relative w-full max-w-md overflow-hidden rounded-t-lg2 bg-paper shadow-lift sm:m-4 sm:rounded-lg2"
                 x-transition:enter="transition ease-out-soft duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-3 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                {{-- ขีดจับสำหรับมือถือ ให้รู้สึกเป็น bottom sheet --}}
                <div class="flex justify-center pt-2.5 sm:hidden">
                    <span class="h-1 w-10 rounded-full bg-line"></span>
                </div>

                <div class="px-6 pb-2 pt-5 sm:pt-7">
                    <div class="flex gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full"
                              :class="{
                                  'bg-red-50 text-red-700': $store.confirm.tone === 'danger',
                                  'bg-accent-light text-accent-ink': $store.confirm.tone === 'warning',
                                  'bg-mist text-brand-dark': $store.confirm.tone === 'info',
                              }">
                            <x-lucide-trash-2 x-show="$store.confirm.tone === 'danger'" class="h-5 w-5" stroke-width="1.9" />
                            <x-lucide-triangle-alert x-show="$store.confirm.tone === 'warning'" class="h-5 w-5" stroke-width="1.9" />
                            <x-lucide-info x-show="$store.confirm.tone === 'info'" class="h-5 w-5" stroke-width="1.9" />
                        </span>

                        <div class="min-w-0 flex-1 pt-0.5">
                            <h3 class="font-display text-[19px] font-bold leading-snug text-ink" x-text="$store.confirm.title"></h3>
                            <p class="mt-1.5 text-[14px] leading-relaxed text-muted" x-text="$store.confirm.body" x-show="$store.confirm.body"></p>

                            {{-- รายการผลกระทบ เช่น จะถูกหักเครดิตหรือไม่ --}}
                            <template x-if="$store.confirm.notes.length">
                                <ul class="mt-3 space-y-1.5 rounded-xl border border-line bg-white/70 p-3">
                                    <template x-for="note in $store.confirm.notes" :key="note">
                                        <li class="flex gap-2 text-[13px] text-muted">
                                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand"></span>
                                            <span x-text="note"></span>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 px-6 pb-6 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-ghost sm:px-5" @click="$store.confirm.dismiss()" x-text="$store.confirm.cancelLabel"></button>

                    <button type="button" x-ref="confirmBtn"
                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition focus:outline-none focus:ring-4"
                            :class="$store.confirm.tone === 'danger'
                                ? 'bg-red-700 hover:bg-red-600 focus:ring-red-500/25'
                                : 'bg-brand-dark hover:bg-brand-deep focus:ring-brand-deep/25'"
                            @click="$store.confirm.accept()"
                            x-text="$store.confirm.confirmLabel"></button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('confirm', {
                    open: false,
                    title: '',
                    body: '',
                    notes: [],
                    tone: 'info',
                    confirmLabel: 'ยืนยัน',
                    cancelLabel: 'ยกเลิก',
                    action: null,

                    ask(options = {}) {
                        this.title = options.title ?? 'ยืนยันการทำรายการ';
                        this.body = options.body ?? '';
                        this.notes = options.notes ?? [];
                        this.tone = options.tone ?? 'info';
                        this.confirmLabel = options.confirmLabel ?? 'ยืนยัน';
                        this.cancelLabel = options.cancelLabel ?? 'ยกเลิก';
                        this.action = options.action ?? null;
                        this.open = true;

                        // กันหน้าเลื่อนอยู่ข้างหลังกล่อง
                        document.body.style.overflow = 'hidden';
                    },

                    accept() {
                        const run = this.action;
                        this.close();
                        if (typeof run === 'function') run();
                    },

                    dismiss() {
                        this.close();
                    },

                    close() {
                        this.open = false;
                        this.action = null;
                        document.body.style.overflow = '';
                    },
                });
            });
        </script>
    @endpush
@endonce
