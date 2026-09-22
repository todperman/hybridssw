{{-- แจ้งเตือนก่อนถึงรอบ
     ถามเซิร์ฟเวอร์ทุก 60 วินาที ส่วนตัวนับถอยหลังเดินฝั่งเบราว์เซอร์
     จะได้ไม่ต้องยิงคำขอทุกวินาทีแค่เพื่ออัปเดตตัวเลข --}}
<div wire:poll.60s>
    @php($booking = $this->booking)

    @if ($booking)
        @php($session = $booking->workoutSession)
        @php($isTrainer = auth()->user()?->trainer && $booking->trainer_id === auth()->user()->trainer->id)

        <div class="reminder"
             wire:key="reminder-{{ $booking->id }}"
             x-data="{
                show: false,
                startsAt: new Date(@js($session->starts_at->toIso8601String())).getTime(),
                left: '',
                started: false,

                init() {
                    // เคยกดปิดรอบนี้ไปแล้วก็ไม่ต้องเด้งซ้ำ แม้จะโหลดหน้าใหม่
                    if (this.seen()) return;

                    this.tick();
                    this.timer = setInterval(() => this.tick(), 1000);

                    // หน่วงนิดหนึ่งให้หน้าวาดเสร็จก่อน การ์ดจะได้เลื่อนเข้ามาอย่างนุ่มนวล
                    setTimeout(() => this.show = true, 400);
                },

                destroy() {
                    clearInterval(this.timer);
                },

                seen() {
                    try {
                        return localStorage.getItem('ssw-reminder-{{ $booking->id }}') === '1';
                    } catch (e) {
                        // โหมดส่วนตัวหรือปิดการเก็บข้อมูลไว้ ถือว่ายังไม่เคยเห็น
                        return false;
                    }
                },

                tick() {
                    const diff = this.startsAt - Date.now();
                    this.started = diff <= 0;

                    const total = Math.max(0, Math.floor(Math.abs(diff) / 1000));
                    const h = Math.floor(total / 3600);
                    const m = Math.floor((total % 3600) / 60);

                    this.left = h > 0 ? (h + ' ชม. ' + m + ' นาที') : (m + ' นาที');
                },

                close() {
                    this.show = false;

                    try {
                        localStorage.setItem('ssw-reminder-{{ $booking->id }}', '1');
                    } catch (e) {
                        // เก็บไม่ได้ก็ยังปิดได้ แค่รอบหน้าที่โหลดใหม่อาจเด้งอีก
                    }

                    setTimeout(() => $wire.dismiss({{ $booking->id }}), 320);
                },
             }"
             x-show="show"
             x-cloak
             x-transition:enter="transition ease-out-soft duration-400"
             x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-0 sm:translate-x-6"
             x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
             x-transition:leave="transition ease-out duration-300"
             x-transition:leave-end="translate-y-4 opacity-0"
             role="status" aria-live="polite">

            {{-- แถบไล่สีด้านบน เป็นตัวบอกว่านี่คือการแจ้งเตือน ไม่ใช่การ์ดเนื้อหาทั่วไป --}}
            <span class="reminder__bar"></span>

            <div class="flex items-start gap-3 p-4">
                <span class="reminder__icon">
                    @svg('lucide-alarm-clock', 'h-5 w-5', ['stroke-width' => '1.9'])
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-brand-deep">
                        <span x-show="! started">ใกล้ถึงรอบของคุณ</span>
                        <span x-show="started" x-cloak>รอบเริ่มแล้ว</span>
                    </p>

                    <p class="mt-0.5 font-display text-[18px] font-extrabold leading-tight text-ink">
                        {{ $session->timeLabel() }}
                    </p>

                    <p class="mt-0.5 text-[12px] text-muted">
                        {{ $session->starts_at->locale('th')->isoFormat('dddd D MMM') }}
                        @if ($session->branch)
                            · {{ $session->branch->name }}
                        @endif
                    </p>

                    {{-- ตัวนับถอยหลังเป็นข้อมูลที่คนอยากรู้ที่สุด จึงเน้นด้วยป้ายสีแยกออกมา --}}
                    <p class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-mine-soft/70 px-3 py-1
                              text-[12px] font-semibold text-mine-ink">
                        <span class="h-1.5 w-1.5 rounded-full bg-mine"></span>
                        <span x-show="! started">อีก <span x-text="left"></span></span>
                        <span x-show="started" x-cloak>เริ่มไปแล้ว <span x-text="left"></span></span>
                    </p>

                    @if ($isTrainer && $this->teamCount > 0)
                        <p class="mt-1.5 text-[12px] text-muted">ลูกทีมของคุณในรอบนี้ {{ $this->teamCount }} คน</p>
                    @endif

                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ $isTrainer ? route('trainer.schedule') : route('member.bookings') }}"
                           wire:navigate @click="close()" class="btn-grad px-4 text-[13px]">
                            ดูรายละเอียด
                        </a>

                        <button type="button" @click="close()" class="btn-ghost px-4 text-[13px]">ไว้ก่อน</button>
                    </div>
                </div>

                <button type="button" @click="close()" aria-label="ปิดการแจ้งเตือน"
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-white/70 hover:text-ink">
                    @svg('lucide-x', 'h-4 w-4', ['stroke-width' => '2.2'])
                </button>
            </div>
        </div>
    @endif
</div>
