<div class="pb-16">
    @php($trainer = $this->trainer())
    @php($count = $this->members->count())
    @php($max = $trainer->maxTeamSize())
    @php($unlimited = $max === null)
    @php($pct = $unlimited ? 100 : ($max > 0 ? min(100, round($count / $max * 100)) : 0))

    <x-page-hero :eyebrow="$trainer->branch->name" title="ทีมของฉัน" pattern="kettlebell" pattern-alt="sandbag"
                 :subtitle="$unlimited ? 'ลูกทีม '.$count.' คน · ไม่จำกัดจำนวน' : 'ลูกทีม '.$count.' จาก '.$max.' คน'">
        <x-slot:action>
            <a href="{{ route('trainer.schedule') }}" wire:navigate
               class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/15 bg-white/10 px-5 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                <x-lucide-calendar-days class="h-4 w-4" stroke-width="1.9" />
                ไปหน้าจอง
            </a>
        </x-slot:action>

        <x-slot:stats>
            {{-- ตัวเลขสามช่องชุดเดียวกับหน้าตารางจอง ทั้งสามค่าอ่านจากข้อมูลที่โหลดมาแล้ว
                 จึงไม่มีคิวรีเพิ่มจากการเพิ่มการ์ดพวกนี้ --}}
            <dl class="mt-5 grid max-w-md grid-cols-3 gap-2 sm:gap-3">
                @foreach ([
                    ['ลูกทีมทั้งหมด', $count],
                    ['กลุ่ม', $this->groups->count()],
                    ['รับได้อีก', $unlimited ? '∞' : max(0, $max - $count)],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-2.5 py-2 backdrop-blur-sm sm:px-3 sm:py-2.5">
                        <dt class="text-[11px] leading-tight text-white/65">{{ $label }}</dt>
                        <dd class="mt-0.5 font-display text-xl font-bold leading-none text-paper sm:text-2xl">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-4 max-w-md">
                <div class="h-1.5 overflow-hidden rounded-full bg-white/15">
                    <div class="h-full rounded-full transition-all duration-700 ease-out-soft
                                {{ $unlimited ? 'bg-brand-light/40' : 'bg-brand-light' }}"
                         style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-1.5 text-[11px] text-white/60">
                    {{ $unlimited ? 'ทีมนี้ไม่จำกัดจำนวนลูกทีม' : 'ใช้โควตาทีมไปแล้ว '.$pct.'%' }}
                </p>
            </div>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-5xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">

        {{-- ชวนลูกทีม --}}
        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">ชวนลูกทีมเข้าทีม</h2>
                <p class="mt-0.5 text-[13px] text-muted">ลูกทีมกรอกข้อมูลเองแล้วเข้าทีมอัตโนมัติ</p>
            </div>

            @if ($trainer->isApproved())
                <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center"
                     x-data="{
                        url: @js($trainer->inviteUrl()),
                        copied: false,
                        canShare: typeof navigator !== 'undefined' && !! navigator.share,
                        async share() {
                            try {
                                await navigator.share({ title: 'เข้าร่วมทีม', url: this.url });
                            } catch (e) {
                                // ผู้ใช้กดยกเลิกแผงแชร์ ไม่ต้องทำอะไรต่อ
                            }
                        },
                        saveQr() {
                            // แปลง SVG ที่เรนเดอร์อยู่แล้วเป็นไฟล์ ไม่ต้องยิงไปหาบริการภายนอก
                            const svg = this.$refs.qr.querySelector('svg');
                            const blob = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' });
                            const a = document.createElement('a');
                            a.href = URL.createObjectURL(blob);
                            a.download = 'invite-qr.svg';
                            a.click();
                            URL.revokeObjectURL(a.href);
                        },
                        async copy() {
                            try {
                                await navigator.clipboard.writeText(this.url);
                            } catch (e) {
                                // เบราว์เซอร์บางตัวบล็อก clipboard API จึงถอยไปใช้วิธีเลือกข้อความแทน
                                this.$refs.field.select();
                                document.execCommand('copy');
                            }
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { tone: 'success', title: 'คัดลอกลิงก์แล้ว', body: 'ส่งให้ลูกทีมได้เลย' },
                            }));
                        },
                     }">

                    <div class="min-w-0 flex-1 space-y-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">ลิงก์คำเชิญ</p>

                        <div class="flex gap-2">
                            <input type="text" readonly :value="url" x-ref="field" @click="$refs.field.select()"
                                   class="glass-input min-w-0 flex-1 px-3.5 py-2.5 font-mono text-[13px] text-ink">

                            <button type="button" @click="copy()" class="btn-grad shrink-0 px-4">
                                <x-lucide-copy x-show="! copied" class="h-4 w-4" stroke-width="1.9" />
                                <x-lucide-check x-show="copied" class="h-4 w-4" stroke-width="2.4" />
                                <span x-text="copied ? 'คัดลอกแล้ว' : 'คัดลอก'"></span>
                            </button>
                        </div>

                        {{-- ปุ่มแชร์ของระบบปฏิบัติการ มีเฉพาะเครื่องที่รองรับ จึงซ่อนไว้ก่อนแล้วค่อยโชว์ --}}
                        <div class="flex flex-wrap gap-2">
                            <button type="button" x-show="canShare" x-cloak @click="share()" class="btn-ghost text-[13px]">
                                <x-lucide-share-2 class="h-4 w-4" stroke-width="1.9" />
                                แชร์ลิงก์
                            </button>

                            <button type="button" @click="saveQr()" class="btn-ghost text-[13px]">
                                <x-lucide-download class="h-4 w-4" stroke-width="1.9" />
                                บันทึก QR
                            </button>
                        </div>

                        <p class="text-[13px] text-muted">ลิงก์นี้ใช้ได้ไม่จำกัดครั้ง ใครเปิดก็สมัครเข้าทีมคุณได้</p>
                    </div>

                    {{-- สร้าง QR ในเครื่อง ไม่ส่งลิงก์คำเชิญออกไปให้บริการภายนอก --}}
                    <figure class="mx-auto shrink-0 text-center sm:mx-0">
                        <div class="h-36 w-36 overflow-hidden rounded-2xl border border-line bg-white p-2.5 shadow-soft"
                             x-ref="qr"
                             role="img" aria-label="QR สำหรับเข้าร่วมทีมของ {{ $trainer->user->name }}">
                            {!! \App\Support\QrCode::svg($trainer->inviteUrl(), 124) !!}
                        </div>

                        <figcaption class="mt-2 text-[11px] leading-tight text-muted">
                            {{ $trainer->user->name }}<br>
                            <span class="text-muted/70">{{ $trainer->branch->name }}</span>
                        </figcaption>
                    </figure>
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-sm text-accent-ink">บัญชียังไม่ได้รับอนุมัติ จึงยังชวนลูกทีมไม่ได้</p>
                </div>
            @endif
        </div>

        {{-- กลุ่มลูกทีม --}}
        <div class="card overflow-hidden">
            <div class="card-head flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-[17px] font-bold text-grad">กลุ่มลูกทีม</h2>
                    <p class="mt-0.5 text-[13px] text-muted">
                        จัดเป็นกลุ่มเพื่อจองทั้งกลุ่มรวดเดียว · เพดานคนต่อกลุ่มกำหนดโดยแอดมิน
                    </p>
                </div>

                @if ($trainer->isApproved())
                    <button wire:click="openGroupForm" class="btn-grad px-5">
                        <x-lucide-plus class="h-4 w-4" />
                        สร้างกลุ่ม
                    </button>
                @endif
            </div>

            @if ($this->groups->isEmpty())
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="dumbbell" class="h-7 w-7 text-brand-deep" />
                    </span>
                    <p class="mt-4 font-display text-lg font-bold text-ink">ยังไม่มีกลุ่ม</p>
                    <p class="mt-1 text-sm text-muted">สร้างกลุ่มไว้ แล้วจองให้ทั้งกลุ่มได้ในคลิกเดียว</p>
                </div>
            @else
                <div class="grid gap-3 p-4 sm:grid-cols-2">
                    @foreach ($this->groups as $group)
                        @php($cap = $group->capacity())
                        @php($n = $group->members_count)
                        @php($full = $n >= $cap)

                        @php($c = $group->palette())

                        <div wire:key="group-{{ $group->id }}"
                             class="overflow-hidden rounded-lg2 border border-line bg-white/70 transition duration-300 ease-out-soft hover:-translate-y-0.5 hover:shadow-lift">

                            {{-- แถบสีประจำกลุ่ม ทำให้แยกกลุ่มได้ด้วยสายตาโดยไม่ต้องอ่านชื่อ --}}
                            <div class="h-1.5" style="background: linear-gradient(90deg, {{ $c['base'] }}, {{ $c['soft'] }})"></div>

                            <div class="p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl font-display text-sm font-bold"
                                      style="background: {{ $c['soft'] }}; color: {{ $c['ink'] }}">
                                    {{ $n }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-display text-[15px] font-bold text-ink">{{ $group->name }}</p>
                                    <p class="truncate text-[12px] text-muted">{{ $group->description ?: 'ไม่มีคำอธิบาย' }}</p>
                                </div>

                                <span class="chip shrink-0"
                                      style="background: {{ $full ? '#ffe2cf' : $c['soft'] }}; color: {{ $full ? '#793d11' : $c['ink'] }}">
                                    {{ $n }}/{{ $cap }}
                                </span>
                            </div>

                            {{-- แถบบอกสัดส่วนที่ใช้ไปของกลุ่ม --}}
                            <div class="mt-3 h-1 overflow-hidden rounded-full bg-track">
                                <div class="h-full rounded-full transition-all duration-700 ease-out-soft"
                                     style="width: {{ $cap > 0 ? min(100, round($n / $cap * 100)) : 0 }}%; background: {{ $c['base'] }}"></div>
                            </div>

                            {{-- รูปสมาชิกซ้อนกัน เห็นได้เร็วว่าใครอยู่ในกลุ่ม --}}
                            <div class="mt-3 flex items-center gap-2">
                                @if ($group->members->isEmpty())
                                    <span class="text-[12px] text-muted/70">ยังไม่มีสมาชิก</span>
                                @else
                                    <div class="flex -space-x-2">
                                        @foreach ($group->members->take(6) as $gm)
                                            <x-avatar :user="$gm->user" size="h-7 w-7" text="text-[10px]" class="ring-2"
                                                      style="--tw-ring-color: {{ $c['soft'] }}" />
                                        @endforeach
                                    </div>
                                    @if ($group->members->count() > 6)
                                        <span class="text-[12px] text-muted">+{{ $group->members->count() - 6 }}</span>
                                    @endif
                                @endif
                            </div>

                            <div class="mt-3 flex items-center gap-2 border-t border-line pt-3">
                                <button wire:click="manageGroup({{ $group->id }})" class="btn-grad-soft flex-1 text-[13px]"
                                        style="--g-soft: {{ $c['soft'] }}; --g-base: {{ $c['base'] }}; --g-ink: {{ $c['ink'] }}">
                                    <x-lucide-users class="h-4 w-4" stroke-width="2" />
                                    จัดสมาชิก
                                </button>

                                <div class="glass-seg shrink-0 gap-0 p-1">

                                <button wire:click="openGroupForm({{ $group->id }})" aria-label="แก้ไขกลุ่ม {{ $group->name }}"
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-white/70 hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" stroke-width="1.9" />
                                </button>

                                <button type="button" aria-label="ลบกลุ่ม {{ $group->name }}"
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-red-50 hover:text-red-700"
                                        @click="$store.confirm.ask({
                                            title: 'ลบกลุ่มนี้',
                                            body: @js($group->name),
                                            notes: ['ลูกทีมยังอยู่ในทีมของคุณตามเดิม', 'การจองที่ทำไปแล้วไม่ถูกกระทบ'],
                                            tone: 'danger',
                                            confirmLabel: 'ลบกลุ่ม',
                                            cancelLabel: 'เก็บไว้ก่อน',
                                            action: () => $wire.deleteGroup({{ $group->id }}),
                                        })">
                                    <x-lucide-trash-2 class="h-4 w-4" stroke-width="1.9" />
                                </button>
                                </div>
                            </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- รายชื่อลูกทีม --}}
        <div class="card overflow-hidden">
            <div class="card-head flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-[17px] font-bold text-grad">รายชื่อลูกทีม</h2>

                {{-- จอเล็กวางช่องค้นหากับปุ่มเพิ่มไว้แถวเดียวกัน ไม่ให้ปุ่มตกไปกินอีกบรรทัด
                     ปุ่มย่อเหลือ "เพิ่ม" บนจอแคบ ช่องค้นหาจะได้เหลือที่พอพิมพ์ --}}
                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <div class="relative min-w-0 flex-1 sm:w-64 sm:flex-none">
                        <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อ ชื่อเล่น หรืออีเมล"
                               class="min-h-[44px] w-full rounded-full border-line bg-white/70 py-2 pl-10 pr-4 text-sm">
                    </div>

                    @if ($trainer->isApproved())
                        <button wire:click="openAddForm" class="btn-grad shrink-0 px-4 text-[13px]">
                            <x-lucide-user-plus class="h-4 w-4" stroke-width="2" />
                            <span class="sm:hidden">เพิ่ม</span>
                            <span class="hidden sm:inline">เพิ่มลูกทีมเอง</span>
                        </button>
                    @endif
                </div>
            </div>

            @if ($this->members->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="dumbbell" class="h-7 w-7 text-brand-deep" />
                    </span>
                    <p class="mt-4 font-display text-lg font-bold text-ink">
                        {{ $search !== '' ? 'ไม่พบลูกทีมที่ค้นหา' : 'ยังไม่มีลูกทีม' }}
                    </p>
                    <p class="mt-1 text-sm text-muted">
                        {{ $search !== '' ? 'ลองคำค้นอื่น' : 'แชร์ลิงก์หรือ QR ด้านบนให้ลูกทีมสมัครเอง' }}
                    </p>
                </div>
            @else
                <div class="grid gap-3 p-4 sm:grid-cols-2">
                    @foreach ($this->members as $member)
                        <div wire:key="member-{{ $member->id }}"
                             class="glass-row items-start gap-3 p-4 transition duration-300 hover:-translate-y-0.5">

                            {{-- แตะที่รูปเพื่อเปลี่ยน เทรนเนอร์ตั้งรูปให้ลูกทีมได้เพราะหน้างานมักถ่ายให้ตอนสมัคร --}}
                            <label class="group relative shrink-0 cursor-pointer">
                                <input type="file" wire:model="photos.{{ $member->id }}" accept="image/*" class="sr-only">

                                <x-avatar :user="$member->user" size="h-12 w-12" text="text-base" class="ring-2 ring-white" />

                                <span class="absolute inset-0 grid place-items-center rounded-full bg-ink/55 opacity-0 transition group-hover:opacity-100">
                                    <x-lucide-camera class="h-4 w-4 text-white" stroke-width="1.9" />
                                </span>

                                <span wire:loading wire:target="photos.{{ $member->id }}"
                                      class="absolute inset-0 grid place-items-center rounded-full bg-ink/60">
                                    <svg class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
                                    </svg>
                                </span>
                            </label>

                            <div class="min-w-0 flex-1">
                                {{-- ชื่อเล่นขึ้นก่อนเพราะเรียกกันหน้างานด้วยชื่อเล่น ชื่อจริงตามด้วยตัวเล็ก --}}
                                @if ($member->user->nickname)
                                    <p class="truncate font-medium text-ink">{{ $member->user->nickname }}</p>
                                    <p class="truncate text-[12px] text-muted">{{ $member->user->name }}</p>
                                @else
                                    <p class="truncate font-medium text-ink">{{ $member->user->name }}</p>
                                @endif

                                <p class="truncate text-[12px] text-muted">{{ $member->user->email }}</p>

                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted/80">
                                    <span class="font-mono text-muted/70">{{ $member->member_code }}</span>

                                    @if ($member->user->phone)
                                        <a href="tel:{{ preg_replace('/\s+/', '', $member->user->phone) }}"
                                           class="inline-flex items-center gap-1 text-brand-deep hover:underline">
                                            <x-lucide-phone class="h-3 w-3" stroke-width="2" />
                                            {{ $member->user->phone }}
                                        </a>
                                    @endif

                                    @if ($member->date_of_birth)
                                        <span>{{ $member->date_of_birth->age }} ปี</span>
                                    @endif

                                    @if ($member->gender)
                                        <span>{{ ['male' => 'ชาย', 'female' => 'หญิง'][$member->gender] ?? $member->gender }}</span>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="chip {{ $member->isActive() ? 'bg-brand-light/40 text-brand-dark' : 'bg-red-100 text-red-800' }}">
                                        {{ $member->isSuspended() ? 'ระงับถึง '.$member->suspended_until?->format('d/m') : $member->status->label() }}
                                    </span>

                                    <span class="chip bg-accent-light text-accent-ink">เครดิต {{ $member->availableCredits() }}</span>

                                    @if ($member->no_show_count > 0)
                                        <span class="chip bg-red-50 text-red-700">ไม่มา {{ $member->no_show_count }}</span>
                                    @endif

                                    @foreach ($member->groups as $g)
                                        @php($gc = $g->palette())
                                        <span class="chip text-[11px]" style="background: {{ $gc['soft'] }}; color: {{ $gc['ink'] }}">
                                            {{ $g->name }}
                                        </span>
                                    @endforeach
                                </div>

                                @error('photos.'.$member->id)
                                    <p class="field-error">{{ $message }}</p>
                                @enderror

                                {{-- ข้อมูลสุขภาพและผู้ติดต่อฉุกเฉินเป็นข้อมูลอ่อนไหว
                                     พับเก็บไว้ ไม่ให้โผล่บนจอที่เปิดค้างอยู่กลางฟิตเนส --}}
                                @php($note = $member->pivot?->trainer_note)
                                <div class="mt-2" x-data="{ open: false, note: @js($note ?? '') }">
                                    <button type="button" @click="open = ! open"
                                            class="inline-flex items-center gap-1 text-[12px] font-medium text-brand-deep hover:underline">
                                        <x-lucide-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="open && 'rotate-180'" stroke-width="2.2" />
                                        <span x-text="open ? 'ซ่อนรายละเอียด' : 'ดูรายละเอียด'"></span>
                                    </button>

                                    <div x-show="open" x-cloak x-collapse class="mt-2 space-y-2 border-t border-line/70 pt-2 text-[12px]">
                                        <dl class="grid gap-1.5">
                                            <div class="flex gap-2">
                                                <dt class="w-24 shrink-0 text-muted">ผู้ติดต่อฉุกเฉิน</dt>
                                                <dd class="min-w-0 flex-1 text-ink">
                                                    @if ($member->emergency_contact_name)
                                                        {{ $member->emergency_contact_name }}
                                                        @if ($member->emergency_contact_phone)
                                                            ·
                                                            <a href="tel:{{ preg_replace('/\s+/', '', $member->emergency_contact_phone) }}"
                                                               class="text-brand-deep hover:underline">{{ $member->emergency_contact_phone }}</a>
                                                        @endif
                                                    @else
                                                        <span class="text-muted/70">ยังไม่ได้กรอก</span>
                                                    @endif
                                                </dd>
                                            </div>

                                            @if ($member->health_note)
                                                <div class="flex gap-2">
                                                    <dt class="w-24 shrink-0 text-muted">โน้ตสุขภาพ</dt>
                                                    <dd class="min-w-0 flex-1 text-ink">{{ $member->health_note }}</dd>
                                                </div>
                                            @endif
                                        </dl>

                                        {{-- โน้ตของเทรนเนอร์ เก็บที่ pivot จึงเห็นเฉพาะทีมนี้ --}}
                                        <div>
                                            <label class="block text-[11px] font-medium text-muted" for="note-{{ $member->id }}">
                                                โน้ตของคุณ (ลูกทีมมองไม่เห็น)
                                            </label>

                                            <textarea id="note-{{ $member->id }}" x-model="note" rows="2" maxlength="500"
                                                      placeholder="เช่น เป้าหมาย ข้อจำกัด เรื่องที่ต้องระวัง"
                                                      class="mt-1 w-full rounded-xl border-line bg-white/70 text-[12px]"></textarea>

                                            <button type="button" @click="$wire.saveNote({{ $member->id }}, note)"
                                                    class="btn-ghost mt-1.5 min-h-[36px] px-3 text-[12px]">บันทึกโน้ต</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- รวบปุ่มเป็นชุดเดียวแบบเดียวกับการ์ดกลุ่ม ไม่ให้ลอยแยกกัน --}}
                            <div class="glass-seg shrink-0 gap-0 p-1">
                            <button type="button" wire:click="editMember({{ $member->id }})"
                                    aria-label="แก้ไขข้อมูลของ {{ $member->user->name }}"
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-white/70 hover:text-brand-deep">
                                <x-lucide-pencil class="h-4 w-4" stroke-width="1.9" />
                            </button>

                            <button type="button" aria-label="นำ {{ $member->user->name }} ออกจากทีม"
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-red-50 hover:text-red-700"
                                    @click="$store.confirm.ask({
                                        title: 'นำออกจากทีม',
                                        body: @js($member->user->name),
                                        notes: ['ประวัติการจองเดิมยังอยู่ครบ', 'ลูกทีมจะหลุดจากทุกกลุ่มของคุณ', 'เข้าทีมใหม่ได้ด้วยลิงก์ชวนเดิม'],
                                        tone: 'danger',
                                        confirmLabel: 'นำออกจากทีม',
                                        cancelLabel: 'เก็บไว้ก่อน',
                                        action: () => $wire.removeMember({{ $member->id }}),
                                    })">
                                <x-lucide-trash-2 class="h-4 w-4" stroke-width="1.9" />
                            </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- แก้ไขข้อมูลลูกทีม --}}
    @if ($editingMemberId)
        <div class="modal-scrim z-[80]"
             @keydown.escape.window="! $store.confirm.open && $wire.closeEditForm()">
            <div class="glass-panel modal-panel">
                <div class="flex justify-center pt-2.5 sm:hidden"><span class="h-1 w-10 rounded-full bg-ink/15"></span></div>

                <form wire:submit="updateMember" class="flex min-h-0 flex-1 flex-col">
                    <div class="glass-head">
                        <h3 class="font-display text-[19px] font-bold text-grad">แก้ไขข้อมูลลูกทีม</h3>
                        <p class="mt-0.5 text-[13px] text-muted">อีเมลกับรหัสผ่านแก้ไม่ได้ ลูกทีมต้องแก้เองในหน้าโปรไฟล์</p>
                    </div>

                    <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <input wire:model.blur="editFirstName" id="editFirstName" type="text" placeholder=" " required
                                       class="peer field-input @error('editFirstName') border-red-400 @enderror">
                                <label for="editFirstName" class="field-label">ชื่อ</label>
                                @error('editFirstName') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <input wire:model.blur="editLastName" id="editLastName" type="text" placeholder=" " required
                                       class="peer field-input @error('editLastName') border-red-400 @enderror">
                                <label for="editLastName" class="field-label">นามสกุล</label>
                                @error('editLastName') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <input wire:model.blur="editNickname" id="editNickname" type="text" placeholder=" "
                                       class="peer field-input @error('editNickname') border-red-400 @enderror">
                                <label for="editNickname" class="field-label">ชื่อเล่น</label>
                                @error('editNickname') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <input wire:model.blur="editPhone" id="editPhone" type="tel" placeholder=" "
                                       class="peer field-input @error('editPhone') border-red-400 @enderror">
                                <label for="editPhone" class="field-label">เบอร์โทร</label>
                                @error('editPhone') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="editDob" class="mb-1 block text-[12px] font-medium text-muted">วันเกิด</label>
                                <input wire:model.blur="editDob" id="editDob" type="date"
                                       class="glass-input h-11 w-full px-3 text-[13px]">
                                @error('editDob') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="editGender" class="mb-1 block text-[12px] font-medium text-muted">เพศ</label>
                                <select wire:model.blur="editGender" id="editGender" class="glass-input h-11 w-full px-3 text-[13px]">
                                    <option value="">ไม่ระบุ</option>
                                    <option value="male">ชาย</option>
                                    <option value="female">หญิง</option>
                                    <option value="other">อื่น ๆ</option>
                                </select>
                                @error('editGender') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <input wire:model.blur="editEmergencyName" id="editEmergencyName" type="text" placeholder=" "
                                       class="peer field-input @error('editEmergencyName') border-red-400 @enderror">
                                <label for="editEmergencyName" class="field-label">ผู้ติดต่อฉุกเฉิน</label>
                                @error('editEmergencyName') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <input wire:model.blur="editEmergencyPhone" id="editEmergencyPhone" type="tel" placeholder=" "
                                       class="peer field-input @error('editEmergencyPhone') border-red-400 @enderror">
                                <label for="editEmergencyPhone" class="field-label">เบอร์ผู้ติดต่อฉุกเฉิน</label>
                                @error('editEmergencyPhone') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="editHealthNote" class="mb-1 block text-[12px] font-medium text-muted">โน้ตสุขภาพ</label>
                            <textarea wire:model.blur="editHealthNote" id="editHealthNote" rows="3" maxlength="1000"
                                      placeholder="เช่น โรคประจำตัว อาการบาดเจ็บเดิม"
                                      class="w-full rounded-xl border-line bg-white/70 text-[13px]"></textarea>
                            @error('editHealthNote') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="glass-foot flex gap-2">
                        <button type="button" wire:click="closeEditForm" class="btn-ghost">ยกเลิก</button>

                        <button type="submit" wire:loading.attr="disabled" class="btn-grad flex-1">
                            <span wire:loading.remove wire:target="updateMember">บันทึก</span>
                            <span wire:loading wire:target="updateMember">กำลังบันทึก…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- เพิ่มลูกทีมเองหน้างาน ไม่ต้องส่งลิงก์ --}}
    @if ($showAddForm)
        <div class="modal-scrim z-[80]"
             @keydown.escape.window="! $store.confirm.open && $wire.closeAddForm()">
            <div class="glass-panel modal-panel max-w-md">
                <div class="flex justify-center pt-2.5 sm:hidden"><span class="h-1 w-10 rounded-full bg-ink/15"></span></div>

                <form wire:submit="addMember">
                    <div class="glass-head">
                        <h3 class="font-display text-[19px] font-bold text-grad">เพิ่มลูกทีมเอง</h3>
                        <p class="mt-0.5 text-[13px] text-muted">กรอกข้อมูลให้ลูกทีมที่อยู่ตรงหน้า ไม่ต้องรอเขากดลิงก์</p>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <input wire:model.blur="newFirstName" id="newFirstName" type="text" placeholder=" " required
                                       class="peer field-input @error('newFirstName') border-red-400 @enderror">
                                <label for="newFirstName" class="field-label">ชื่อ</label>
                                @error('newFirstName') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <input wire:model.blur="newLastName" id="newLastName" type="text" placeholder=" " required
                                       class="peer field-input @error('newLastName') border-red-400 @enderror">
                                <label for="newLastName" class="field-label">นามสกุล</label>
                                @error('newLastName') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="field">
                            <input wire:model.blur="newNickname" id="newNickname" type="text" placeholder=" "
                                   class="peer field-input @error('newNickname') border-red-400 @enderror">
                            <label for="newNickname" class="field-label">ชื่อเล่น (ไม่บังคับ)</label>
                            @error('newNickname') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <input wire:model.blur="newEmail" id="newEmail" type="email" placeholder=" " required
                                       class="peer field-input @error('newEmail') border-red-400 @enderror">
                                <label for="newEmail" class="field-label">อีเมล</label>
                                @error('newEmail') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <input wire:model.blur="newPhone" id="newPhone" type="tel" placeholder=" "
                                       class="peer field-input @error('newPhone') border-red-400 @enderror">
                                <label for="newPhone" class="field-label">เบอร์โทร (ไม่บังคับ)</label>
                                @error('newPhone') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <ul class="space-y-1.5 rounded-2xl border border-accent/40 bg-accent-light/40 p-3.5 text-[12px] text-accent-ink">
                            <li>ระบบไม่ตั้งรหัสผ่านให้ ลูกทีมกด "ลืมรหัสผ่าน" เพื่อตั้งเองครั้งแรก</li>
                        </ul>
                    </div>

                    <div class="glass-foot flex gap-2">
                        <button type="button" wire:click="closeAddForm" class="btn-ghost">ยกเลิก</button>

                        <button type="submit" wire:loading.attr="disabled" class="btn-grad flex-1">
                            <span wire:loading.remove wire:target="addMember">เพิ่มเข้าทีม</span>
                            <span wire:loading wire:target="addMember">กำลังเพิ่ม…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ฟอร์มสร้าง / แก้ไขกลุ่ม --}}
    @if ($showGroupForm)
        <div class="modal-scrim z-[80]"
             @keydown.escape.window="! $store.confirm.open && $wire.closeGroupForm()">
            <div class="glass-panel modal-panel max-w-md">
                <div class="flex justify-center pt-2.5 sm:hidden"><span class="h-1 w-10 rounded-full bg-line"></span></div>

                <form wire:submit="saveGroup">
                    @php($picked = \App\Support\GroupPalette::get($groupColor))

                    <div class="h-1.5" style="background: linear-gradient(90deg, {{ $picked['base'] }}, {{ $picked['soft'] }})"></div>

                    <div class="border-b border-line bg-white/70 px-5 py-4">
                        <h3 class="font-display text-lg font-bold text-ink">
                            {{ $editingGroupId ? 'แก้ไขกลุ่ม' : 'สร้างกลุ่มใหม่' }}
                        </h3>
                        <p class="mt-0.5 text-[13px] text-muted">เพดานจำนวนคนต่อกลุ่มกำหนดโดยแอดมิน ปรับที่นี่ไม่ได้</p>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <div class="field">
                            <input wire:model="groupName" id="groupName" type="text" placeholder=" " autofocus
                                   class="peer field-input @error('groupName') border-red-400 @enderror">
                            <label for="groupName" class="field-label">ชื่อกลุ่ม</label>
                            @error('groupName') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="field">
                            <input wire:model="groupDescription" id="groupDescription" type="text" placeholder=" "
                                   class="peer field-input @error('groupDescription') border-red-400 @enderror">
                            <label for="groupDescription" class="field-label">คำอธิบาย (ไม่บังคับ)</label>
                            @error('groupDescription') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- สีประจำกลุ่ม ใช้แยกกลุ่มด้วยสายตาในหน้ารายชื่อและบนป้ายของลูกทีม --}}
                        <div>
                            <p class="mb-2 text-[13px] font-medium text-ink">สีประจำกลุ่ม</p>

                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Support\GroupPalette::COLORS as $key => $c)
                                    <button type="button" wire:key="swatch-{{ $key }}" wire:click="setGroupColor('{{ $key }}')"
                                            title="{{ $c['label'] }}" aria-label="{{ $c['label'] }}"
                                            aria-pressed="{{ $groupColor === $key ? 'true' : 'false' }}"
                                            class="grid h-11 w-11 place-items-center rounded-full transition duration-200 ease-out-soft hover:scale-105
                                                {{ $groupColor === $key ? 'ring-2 ring-offset-2 ring-offset-paper' : '' }}"
                                            style="background: {{ $c['soft'] }}; {{ $groupColor === $key ? '--tw-ring-color: '.$c['base'].';' : '' }}">
                                        <span class="h-5 w-5 rounded-full" style="background: {{ $c['base'] }}"></span>
                                    </button>
                                @endforeach
                            </div>

                            @error('groupColor') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <p class="rounded-xl border border-line bg-white/70 px-4 py-3 text-[13px] text-muted">
                            กลุ่มในสาขานี้รับได้สูงสุด
                            <span class="font-semibold text-ink">{{ $trainer->branch->max_group_size }} คน</span>
                            ต่อกลุ่ม
                        </p>
                    </div>

                    <div class="flex gap-2 border-t border-line bg-white/70 px-5 py-4">
                        <button type="button" wire:click="closeGroupForm" class="btn-ghost">ยกเลิก</button>
                        <button type="submit" class="btn-grad flex-1">
                            {{ $editingGroupId ? 'บันทึก' : 'สร้างกลุ่ม' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- แผงจัดสมาชิกในกลุ่ม --}}
    @if ($this->managingGroup)
        @php($group = $this->managingGroup)
        @php($cap = $group->capacity())

        <div class="modal-scrim z-[80]"
             @keydown.escape.window="! $store.confirm.open && $wire.manageGroup(null)">
            <div class="glass-panel modal-panel">
                <div class="flex justify-center pt-2.5 sm:hidden"><span class="h-1 w-10 rounded-full bg-line"></span></div>

                @php($gc = $group->palette())
                @php($used = $group->members->count())
                @php($full = $used >= $cap)

                <div class="glass-head flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.12em]"
                           style="color: {{ $gc['ink'] }}">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $gc['base'] }}"></span>
                            กลุ่มลูกทีม
                        </p>
                        <h3 class="mt-0.5 truncate font-display text-[20px] font-extrabold text-ink">{{ $group->name }}</h3>
                        @if ($group->description)
                            <p class="truncate text-[13px] text-muted">{{ $group->description }}</p>
                        @endif
                    </div>

                    {{-- วงแหวนบอกสัดส่วนที่ใช้ไป อ่านเร็วกว่าตัวเลขล้วน ชุดเดียวกับโมดัลจอง --}}
                    <div class="flex shrink-0 items-center gap-3">
                        @php($pct = $cap > 0 ? min(100, round($used / $cap * 100)) : 0)
                        <div class="grid h-12 w-12 place-items-center rounded-full"
                             style="background: conic-gradient({{ $gc['base'] }} {{ $pct * 3.6 }}deg, rgba(28,47,58,.10) 0deg)">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-white/90 font-display text-[12px] font-bold"
                                  style="color: {{ $gc['ink'] }}">{{ $used }}/{{ $cap }}</span>
                        </div>

                        <button wire:click="manageGroup(null)" aria-label="ปิด"
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-white/70 hover:text-ink">
                            <x-lucide-x class="h-4 w-4" stroke-width="2.2" />
                        </button>
                    </div>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">อยู่ในกลุ่มนี้</p>

                        @if ($group->members->isEmpty())
                            <p class="rounded-2xl border border-dashed border-line px-4 py-6 text-center text-sm text-muted">
                                ยังไม่มีสมาชิกในกลุ่ม เลือกจากรายชื่อด้านล่างได้เลย
                            </p>
                        @else
                            <ul class="space-y-1.5">
                                @foreach ($group->members as $m)
                                    <li class="glass-row glass-row-on gap-3 px-3 py-2"
                                        style="--tw-ring-color: {{ $gc['base'] }}">
                                        <x-avatar :user="$m->user" size="h-9 w-9" text="text-[11px]" class="ring-2 ring-white/80" />

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-ink">{{ $m->user->displayName() }}</span>
                                            @if ($m->user->nickname)
                                                <span class="block truncate text-[11px] text-muted">{{ $m->user->name }}</span>
                                            @endif
                                        </span>

                                        <button type="button"
                                                aria-label="นำ {{ $m->user->name }} ออกจากกลุ่ม"
                                                class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-red-50 hover:text-red-700"
                                                @click="$store.confirm.ask({
                                                    title: 'นำออกจากกลุ่ม',
                                                    body: @js($m->user->name),
                                                    notes: ['ลูกทีมยังอยู่ในทีมของคุณตามเดิม', 'การจองที่ทำไปแล้วไม่ถูกกระทบ', 'เพิ่มกลับเข้ากลุ่มได้ทุกเมื่อ'],
                                                    tone: 'danger',
                                                    confirmLabel: 'นำออกจากกลุ่ม',
                                                    cancelLabel: 'เก็บไว้ก่อน',
                                                    action: () => $wire.removeFromGroup({{ $m->id }}),
                                                })">
                                            <x-lucide-minus class="h-4 w-4" stroke-width="2.4" />
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div>
                        <p class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">
                            เพิ่มจากทีมของคุณ
                            @if ($full)
                                <span class="chip bg-accent-light text-[10px] normal-case tracking-normal text-accent-ink">กลุ่มเต็มแล้ว</span>
                            @endif
                        </p>

                        @if ($this->assignableMembers->isEmpty())
                            <p class="rounded-2xl border border-dashed border-line px-4 py-6 text-center text-sm text-muted">
                                ลูกทีมทุกคนอยู่ในกลุ่มนี้แล้ว
                            </p>
                        @else
                            <ul class="space-y-1.5">
                                @foreach ($this->assignableMembers as $m)
                                    <li class="glass-row gap-3 px-3 py-2 {{ $full ? 'opacity-50' : '' }}">
                                        <x-avatar :user="$m->user" size="h-9 w-9" text="text-[11px]" class="ring-2 ring-white/80" />

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-ink">{{ $m->user->displayName() }}</span>
                                            @if ($m->user->nickname)
                                                <span class="block truncate text-[11px] text-muted">{{ $m->user->name }}</span>
                                            @endif
                                        </span>

                                        <button wire:click="addToGroup({{ $m->id }})"
                                                @disabled($full)
                                                class="btn-grad-soft shrink-0 px-4 text-[13px] disabled:cursor-not-allowed disabled:opacity-40"
                                                style="--g-soft: {{ $gc['soft'] }}; --g-base: {{ $gc['base'] }}; --g-ink: {{ $gc['ink'] }}">
                                            <x-lucide-plus class="h-4 w-4" stroke-width="2.4" />
                                            เพิ่ม
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="glass-foot">
                    <button wire:click="manageGroup(null)" class="btn-grad w-full">เสร็จสิ้น</button>
                </div>
            </div>
        </div>
    @endif
</div>
