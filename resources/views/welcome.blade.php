<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans text-ink antialiased">
        @php($stations = \App\Support\TrainingStations::ALL)

        <div class="relative min-h-screen overflow-hidden bg-paper">
            <div class="pointer-events-none absolute -left-52 -top-52 h-[34rem] w-[34rem] rounded-full bg-brand-light/40 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-64 -right-40 h-[38rem] w-[38rem] rounded-full bg-accent-light/70 blur-3xl"></div>

            <header class="relative mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-10">
                <x-application-logo />

                <div class="flex items-center gap-2">
                    <span class="chip hidden border border-line bg-white/70 text-muted sm:inline-flex">
                        <span class="mr-2 h-1.5 w-1.5 rounded-full bg-brand"></span>
                        ระบบจองชั่วโมงการเล่น
                    </span>

                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="btn-primary">เข้าหน้าของฉัน</a>
                    @endauth
                </div>
            </header>

            <main class="relative">

                {{-- ---------- ส่วนเปิด ---------- --}}
                <section class="mx-auto max-w-7xl px-6 pb-20 pt-6 lg:px-10 lg:pt-14">
                    <h1 class="max-w-3xl font-display text-[2.6rem] font-extrabold heading-th text-ink sm:text-5xl lg:text-6xl">
                        8 สถานี · 8 กิโลเมตร<br>
                        <span class="text-brand-deep">ลำดับเดิมทุกครั้ง</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-muted">
                        โปรแกรมฝึกสไตล์ fitness racing วิ่งสลับสถานีฟังก์ชันนอล
                        สร้างทั้งความอึดและความแข็งแรงไปพร้อมกัน
                        ไม่ว่าคุณจะเตรียมลงแข่งหรือแค่อยากฟิตขึ้น
                    </p>

                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="{{ route('trainer.register') }}" wire:navigate class="btn-primary px-7 py-3 text-base">สมัครเป็นเทรนเนอร์</a>
                        <a href="{{ route('login') }}" wire:navigate class="btn-ghost px-6 py-3 text-base">เข้าสู่ระบบ</a>
                    </div>

                    {{-- แถบไอคอนสถานีแบบเลื่อนต่อเนื่อง บอกใบ้ว่าเนื้อหาข้างล่างมีอะไร --}}
                    {{-- ชื่อสถานีเรียงต่อกันคั่นด้วยสัญลักษณ์เล็ก ๆ สลับเข้ม-อ่อน
                         ไม่ใส่ไอคอนอุปกรณ์เพราะขนาดเท่าตัวอักษรแล้วอ่านไม่ออก กลายเป็นจุดรก --}}
                    <div class="marquee mt-14" aria-hidden="true">
                        <div class="marquee__track">
                            @foreach (array_merge($stations, $stations) as $i => $s)
                                <span class="marquee__name {{ $i % 2 === 0 ? 'is-strong' : '' }}">{{ $s['name'] }}</span>

                                <svg class="marquee__sep" viewBox="0 0 22 16" fill="none" aria-hidden="true">
                                    <path d="M3 5v6" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                    <path d="M9 2v12" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity=".75"/>
                                    <path d="M15 6v4" stroke="#ffd758" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ---------- สามขั้นของการฝึก ---------- --}}
                <section class="relative border-y border-line bg-white/50 py-20 backdrop-blur-sm">
                    <div class="mx-auto max-w-7xl px-6 lg:px-10">
                        <h2 class="max-w-2xl font-display text-3xl font-extrabold heading-th text-ink sm:text-4xl">
                            เริ่มจากพื้นฐาน แล้วค่อย ๆ ไปให้ไกลกว่าเดิม
                        </h2>

                        <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-muted">
                            ทุกโปรแกรมเดินไปตามลำดับเดียวกัน เพื่อให้ร่างกายพร้อมก่อนเพิ่มความหนัก
                        </p>

                        <div class="mt-10 grid gap-5 lg:grid-cols-3">
                            @foreach ([
                                ['Move', 'ขยับให้ถูกท่า', 'ฝึกท่าพื้นฐานของทุกสถานีให้ถูกต้องและมั่นคง ในจังหวะที่ร่างกายของคุณรับได้'],
                                ['Build', 'สร้างแรงที่ใช้ได้จริง', 'ผสานการวิ่งเข้ากับงานดัน ดึง แบก และยก เพื่อแรงที่ใช้ได้แม้ร่างกายจะล้าแล้ว'],
                                ['Evolve', 'เพิ่มความท้าทายทีละขั้น', 'ค่อย ๆ ต่อยอดจากเซสชันสั้น ๆ ไปจนถึงเส้นทางเต็ม 8 สถานี ในแบบของคุณ'],
                            ] as $i => $stage)
                                {{-- ไม่ใช้ overflow-hidden ที่การ์ด ไม่งั้นเลขขั้นตอนจะถูกตัดหัว
                                     วางเลขให้อยู่ในกรอบพอดีแทนการปล่อยล้นแล้วค่อยคลิป --}}
                                <div class="card relative p-6">
                                    <span class="pointer-events-none absolute right-4 top-2 font-display text-[4.5rem] font-extrabold leading-none text-brand-light/20">
                                        {{ $i + 1 }}
                                    </span>

                                    <p class="relative text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-deep">
                                        ขั้นที่ {{ $i + 1 }}
                                    </p>

                                    <h3 class="relative mt-1 font-display text-2xl font-extrabold text-ink">{{ $stage[0] }}</h3>
                                    <p class="relative mt-0.5 text-[15px] font-medium text-ink">{{ $stage[1] }}</p>
                                    <p class="relative mt-2.5 text-sm leading-relaxed text-muted">{{ $stage[2] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ---------- แปดสถานี ---------- --}}
                <section class="py-20">
                    <div class="mx-auto max-w-7xl px-6 lg:px-10"
                         x-data="{
                            stations: @js($stations),
                            active: 0,
                            showAll: false,
                            get s() { return this.stations[this.active] },
                         }">

                        <h2 class="max-w-2xl font-display text-3xl font-extrabold heading-th text-ink sm:text-4xl">
                            วิ่ง 8 กิโลเมตร ผ่าน 8 สถานี ในลำดับเดียวกันทุกครั้ง
                        </h2>

                        <p class="mt-3 max-w-xl text-[15px] leading-relaxed text-muted">
                            วิ่ง 1 กม. แล้วเข้าสถานี สลับกันจนครบ 8 รอบ แตะแต่ละจุดเพื่อดูว่าสถานีนั้นฝึกอะไร
                        </p>

                        <div class="card mt-10 p-5 sm:p-8">

                            {{-- แถบลำดับสถานี จุดตัวเลขเชื่อมกันด้วยเส้นบาง ชื่ออยู่ใต้จุด
                                 จอแคบเลื่อนแนวนอนได้ เพราะแปดจุดพร้อมชื่อยาวเกินความกว้างมือถือ --}}
                            <div class="route-scroll">
                                <ol class="route-steps">
                                    @foreach ($stations as $i => $st)
                                        <li class="route-step">
                                            <button type="button" @click="active = {{ $i }}"
                                                    class="route-step__dot"
                                                    :class="active === {{ $i }} && 'is-active'"
                                                    :aria-current="active === {{ $i }} ? 'step' : null">
                                                {{ $i + 1 }}
                                            </button>

                                            <span class="route-step__label"
                                                  :class="active === {{ $i }} && 'is-active'">{{ $st['name'] }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>

                            {{-- รายละเอียดสถานีที่เลือก --}}
                            <div class="route-panel">
                                <p class="route-panel__eyebrow"
                                   x-text="'วิ่ง 1 กม. แล้วเข้าสถานีที่ ' + (active + 1) + ' จาก ' + stations.length"></p>

                                <div class="mt-5 grid gap-6 sm:grid-cols-2 sm:gap-10">
                                    <div>
                                        <h3 class="font-display text-[2.4rem] font-extrabold leading-none heading-th text-ink sm:text-[2.75rem]"
                                            x-text="s.name"></h3>
                                        <p class="mt-2 text-[15px] font-medium text-ink/70" x-text="s.thai"></p>
                                    </div>

                                    <div>
                                        <p class="flex flex-wrap items-center gap-3">
                                            <span class="font-display text-[1.9rem] font-extrabold leading-none heading-th text-ink"
                                                  x-text="s.spec"></span>
                                            <span class="chip bg-accent-light/70 text-accent-ink" x-text="s.focus"></span>
                                        </p>

                                        <p class="mt-3 text-[15px] leading-relaxed text-muted" x-text="s.detail"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <button type="button" @click="active--" :disabled="active === 0"
                                        class="btn-ghost text-[13px] disabled:cursor-not-allowed disabled:opacity-40">
                                    สถานีก่อนหน้า
                                </button>

                                <button type="button" @click="active++" :disabled="active === stations.length - 1"
                                        class="btn-ghost text-[13px] disabled:cursor-not-allowed disabled:opacity-40">
                                    สถานีถัดไป
                                </button>

                                <button type="button" @click="showAll = ! showAll"
                                        class="btn-solid ms-auto text-[13px]"
                                        x-text="showAll ? 'ย่อเส้นทาง' : 'ดูทั้งเส้นทาง'"></button>
                            </div>

                            {{-- ดูทั้งเส้นทางทีเดียว สำหรับคนที่อยากเทียบทุกสถานีพร้อมกัน --}}
                            <div x-show="showAll" x-cloak x-collapse class="mt-5 border-t border-line pt-5">
                                <ul class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($stations as $i => $st)
                                        <li>
                                            <button type="button" @click="active = {{ $i }}"
                                                    class="route-all"
                                                    :class="active === {{ $i }} && 'is-active'">
                                                <span class="route-all__n">{{ $i + 1 }}</span>

                                                <span class="min-w-0 flex-1 text-start">
                                                    <span class="block truncate text-[14px] font-semibold text-ink">{{ $st['name'] }}</span>
                                                    <span class="block truncate text-[12px] text-muted">{{ $st['thai'] }}</span>
                                                </span>

                                                <span class="shrink-0 text-[13px] font-medium text-brand-deep">{{ $st['spec'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ---------- ปรับสัดส่วนการฝึก ---------- --}}
                <section class="relative border-y border-line bg-white/50 py-20 backdrop-blur-sm">
                    <div class="mx-auto max-w-4xl px-6 lg:px-10"
                         x-data="{
                            strength: 50,
                            get endurance() { return 100 - this.strength },
                            get label() {
                                if (this.strength <= 30) return 'เน้นความอึด';
                                if (this.strength >= 70) return 'เน้นความแข็งแรง';
                                return 'สมดุล';
                            },
                            get sample() {
                                if (this.strength <= 30) return [
                                    'วิ่ง 2 กม. ต่อเนื่อง',
                                    'วิ่ง 1 กม. แล้วพายเรือ 500 ม. × 2 รอบ',
                                    'เบอร์พีกระโดดไกล 40 ม.',
                                ];
                                if (this.strength >= 70) return [
                                    'ดันเลื่อน 50 ม. × 4 รอบ',
                                    'แบกถุงทรายย่อขา 100 ม.',
                                    'หิ้วน้ำหนักเดิน 200 ม. × 3 รอบ',
                                ];
                                return [
                                    'วิ่ง 1 กม. แล้วดันเลื่อน 50 ม. × 2 รอบ',
                                    'วิ่ง 1 กม. แล้วหิ้วน้ำหนักเดิน 200 ม.',
                                    'ขว้างลูกบอลกำแพง 50 ครั้ง',
                                ];
                            },
                         }">

                        <h2 class="font-display text-3xl font-extrabold heading-th text-ink sm:text-4xl">
                            การฝึกแบบ hybrid ของคุณหน้าตาแบบไหน
                        </h2>

                        <p class="mt-3 text-[15px] leading-relaxed text-muted">
                            เลื่อนเพื่อปรับสัดส่วนระหว่างความอึดกับความแข็งแรง แล้วดูตัวอย่างเซสชันที่ได้
                        </p>

                        <div class="card mt-8 p-6 sm:p-8">
                            <div class="flex items-center justify-between text-[13px] font-medium">
                                <span class="text-brand-deep">ความอึด <span x-text="endurance"></span>%</span>
                                <span class="chip bg-brand-light/30 text-brand-dark" x-text="label"></span>
                                <span class="text-accent-ink">ความแข็งแรง <span x-text="strength"></span>%</span>
                            </div>

                            <label class="sr-only" for="mix">สัดส่วนความแข็งแรง</label>
                            <input id="mix" type="range" min="0" max="100" step="5" x-model.number="strength"
                                   class="mix-slider mt-4">

                            <ul class="mt-7 space-y-2">
                                <template x-for="line in sample" :key="line">
                                    <li class="glass-row gap-3 px-4 py-3 text-sm text-ink">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand"></span>
                                        <span x-text="line"></span>
                                    </li>
                                </template>
                            </ul>

                            <p class="mt-4 text-[12px] text-muted/80">
                                เป็นตัวอย่างเพื่ออธิบายแนวคิดเท่านั้น โปรแกรมจริงจะปรับตามแต่ละคน
                            </p>
                        </div>
                    </div>
                </section>

                {{-- ---------- ปิดท้าย ---------- --}}
                <section class="mx-auto max-w-7xl px-6 py-20 lg:px-10">
                    <div class="relative overflow-hidden rounded-[28px] bg-[#00334a] px-6 py-14 text-center sm:px-12">
                        <div class="absolute inset-0 overflow-hidden">
                            <div class="hero-surface absolute inset-0"></div>
                            <div class="absolute inset-0 grid-lines opacity-[0.28]"></div>
                            <span class="hero-wordmark">Hybrid<br>Workout</span>
                            <div class="hero-grain pointer-events-none absolute inset-0"></div>
                        </div>

                        <div class="relative">
                            <h2 class="font-display text-3xl font-extrabold heading-th text-paper sm:text-4xl">
                                พร้อมเริ่มเมื่อไหร่ ระบบพร้อมแล้ว
                            </h2>

                            <p class="mx-auto mt-3 max-w-lg text-[15px] leading-relaxed text-white/75">
                                เทรนเนอร์สมัครเข้าระบบ สร้างทีมของตัวเอง แล้วจองชั่วโมงให้ลูกทีมได้เอง
                            </p>

                            <div class="mt-8 flex flex-wrap justify-center gap-3">
                                <a href="{{ route('trainer.register') }}" wire:navigate
                                   class="inline-flex min-h-[48px] items-center rounded-full bg-paper px-7 text-[15px] font-semibold text-brand-dark shadow-soft transition hover:bg-white">
                                    สมัครเป็นเทรนเนอร์
                                </a>

                                <a href="{{ route('login') }}" wire:navigate
                                   class="inline-flex min-h-[48px] items-center rounded-full border border-white/25 bg-white/10 px-6 text-[15px] font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                                    เข้าสู่ระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="relative border-t border-line py-8 text-center text-xs text-muted">
                Srisawan Hybrid Workout
            </footer>
        </div>

        {{-- หน้านี้ไม่ได้เรนเดอร์คอมโพเนนต์ Livewire ตัวไหนเลย สคริปต์จึงไม่ถูกฉีดเข้ามาเอง
             ต้องเรียกเอง ไม่งั้นจะไม่มี Alpine และ wire:navigate ก็ไม่ทำงาน --}}
        @livewireScripts
    </body>
</html>
