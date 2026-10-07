<div class="pb-48 sm:pb-32">
    @php($branch = $this->branch)
    @php($selected = \Carbon\CarbonImmutable::parse($date))
    @php($trainee = $this->isTrainee())
    @php($maxHours = (int) $branch->max_booking_hours)
    @php($maxTrainees = (int) $branch->max_trainees)
    @php($priced = (float) $branch->hourly_rate > 0)

    <x-page-hero :eyebrow="$trainee ? 'จองยิม' : 'จองให้ลูกเทรน'"
                 :title="$trainee ? 'จองยิมทั้งยิมเป็นรายชั่วโมง' : 'จองยิมให้ลูกเทรน'"
                 :subtitle="$branch->name" pattern="rower" pattern-alt="kettlebell">
        <x-slot:stats>
            <p class="mt-4 flex max-w-md items-start gap-2 text-[13px] leading-relaxed text-white/80">
                @svg('lucide-info', 'mt-0.5 h-4 w-4 shrink-0 text-brand-light', ['stroke-width' => '2'])
                หนึ่งการจองได้ใช้ทั้งยิม {{ $maxTrainees > 1 ? "ผู้เข้าร่วม 1–{$maxTrainees} คน" : '' }}
                ระบบกันเวลาไว้ให้ระหว่างรอชำระเงิน และยืนยันเมื่อชำระสำเร็จ
            </p>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-6 sm:px-6">
        @unless ($priced)
            <div class="card border-accent/40 bg-accent-light/60 px-5 py-4 text-sm text-accent-ink">
                สาขานี้ยังไม่ได้ตั้งราคาต่อชั่วโมง จึงยังเปิดจองไม่ได้ กรุณาติดต่อแอดมิน
            </div>
        @endunless

        {{-- 1. วันและจำนวนชั่วโมง --}}
        <section class="card overflow-hidden">
            <div class="card-head flex items-center justify-between gap-3">
                <h2 class="font-display text-[17px] font-bold text-grad">1. วันและระยะเวลา</h2>
                <span class="text-xs text-muted">จองล่วงหน้าได้ {{ $branch->booking_window_days }} วัน</span>
            </div>

            <div class="space-y-4 p-4 sm:p-5">
                <div class="scroll-quiet -mx-0.5 flex snap-x gap-1.5 overflow-x-auto px-0.5 pb-0.5" role="tablist" aria-label="เลือกวัน">
                    @foreach ($this->days as $d)
                        @php($ymd = $d->toDateString())
                        @php($active = $ymd === $date)
                        <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
                                wire:click="selectDay('{{ $ymd }}')" wire:key="day-{{ $ymd }}"
                                class="flex min-w-[3.6rem] shrink-0 snap-start flex-col items-center rounded-2xl px-2 py-2.5 transition
                                       {{ $active ? 'bg-brand-dark text-white shadow-soft' : 'text-ink hover:bg-mist' }}">
                            <span class="text-[11px] leading-none {{ $active ? 'text-white/70' : 'text-muted' }}">
                                {{ $d->isToday() ? 'วันนี้' : $d->locale('th')->isoFormat('dd') }}
                            </span>
                            <span class="mt-1 font-display text-lg font-bold leading-none">{{ $d->format('j') }}</span>
                            <span class="mt-1 text-[10px] leading-none {{ $active ? 'text-white/60' : 'text-muted' }}">{{ $d->locale('th')->isoFormat('MMM') }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-sm text-muted">ระยะเวลา</span>
                    @for ($h = 1; $h <= $maxHours; $h++)
                        <button type="button" wire:click="setHours({{ $h }})" wire:key="h-{{ $h }}"
                                aria-pressed="{{ $hours === $h ? 'true' : 'false' }}"
                                class="min-h-[40px] rounded-full border px-4 text-sm font-medium transition
                                       {{ $hours === $h ? 'border-brand-dark bg-brand-dark text-white' : 'border-line bg-white/60 text-ink hover:bg-mist' }}">
                            {{ $h }} ชม.
                        </button>
                    @endfor
                </div>
            </div>
        </section>

        {{-- 2. เวลาเริ่ม --}}
        <section class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">2. เวลาเริ่ม</h2>
                <p class="mt-0.5 text-xs text-muted">{{ $selected->locale('th')->isoFormat('dddd D MMMM') }} · แสดงเฉพาะช่วงที่ยิมว่างครบ {{ $hours }} ชั่วโมง{{ $trainee ? '' : ' และคุณว่าง' }}</p>
            </div>

            <div class="p-4 sm:p-5">
                @if (empty($this->startTimes))
                    <p class="py-6 text-center text-sm text-muted">วันนี้ไม่มีช่วงเวลาที่ว่างครบ {{ $hours }} ชั่วโมง ลองเลือกวันอื่นหรือลดจำนวนชั่วโมง</p>
                    @unless ($trainee)
                        <p class="text-center text-sm">
                            <a href="{{ route('trainer.availability') }}" wire:navigate class="font-medium text-brand-deep underline">ตั้งเวลาว่างของคุณ</a>
                        </p>
                    @endunless
                @else
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                        @foreach ($this->startTimes as $t)
                            @php($hm = $t->format('H:i'))
                            <button type="button" wire:click="pickStart('{{ $hm }}')" wire:key="t-{{ $date }}-{{ $hm }}"
                                    aria-pressed="{{ $start === $hm ? 'true' : 'false' }}"
                                    class="min-h-[48px] rounded-xl border text-center transition
                                           {{ $start === $hm ? 'border-brand-dark bg-brand-dark text-white shadow-soft' : 'border-line bg-white/60 text-ink hover:bg-mist' }}">
                                <span class="block font-display text-[15px] font-bold leading-tight">{{ $hm }}</span>
                                <span class="block text-[11px] leading-tight {{ $start === $hm ? 'text-white/70' : 'text-muted' }}">ถึง {{ $t->addHours($hours)->format('H:i') }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- 3. ผู้เข้าร่วม --}}
        <section class="card overflow-hidden">
            <div class="card-head flex items-center justify-between gap-3">
                <h2 class="font-display text-[17px] font-bold text-grad">3. ผู้เข้าร่วม</h2>
                <span class="chip bg-brand-light/40 text-brand-dark">{{ count($memberIds) }}/{{ $maxTrainees }} คน</span>
            </div>

            <div class="space-y-4 p-4 sm:p-5">
                @if ($this->participants->isNotEmpty())
                    <ul class="divide-y divide-line rounded-xl border border-line bg-white/60">
                        @foreach ($this->participants as $m)
                            <li wire:key="p-{{ $m->id }}" class="flex items-center gap-3 px-3 py-2.5">
                                <x-avatar :user="$m->user" size="h-9 w-9" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $m->user->displayName() }}</p>
                                    <p class="text-xs text-muted">{{ $m->member_code }}</p>
                                </div>
                                @if ($trainee && $m->id === auth()->user()->member->id)
                                    <span class="chip bg-mist text-muted">คุณ</span>
                                @else
                                    <button type="button" wire:click="removeMember({{ $m->id }})"
                                            class="grid h-9 w-9 place-items-center rounded-full text-muted hover:bg-mist hover:text-ink"
                                            aria-label="เอา {{ $m->user->displayName() }} ออก">
                                        @svg('lucide-x', 'h-4 w-4')
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-muted">ยังไม่มีผู้เข้าร่วม เพิ่มลูกเทรนอย่างน้อย 1 คน</p>
                @endif

                @if ($lookupError)
                    <p class="text-sm text-red-600" role="alert">{{ $lookupError }}</p>
                @endif

                @if (count($memberIds) < $maxTrainees)
                    <form wire:submit="addMember" class="space-y-1.5">
                        <label for="lookup" class="text-sm font-medium text-ink">เพิ่ม{{ $trainee ? 'เพื่อน' : 'ลูกเทรน' }}ด้วยรหัสสมาชิกหรือเบอร์โทร</label>
                        <div class="flex gap-2">
                            <input id="lookup" type="text" wire:model="lookup" autocomplete="off" inputmode="text"
                                   placeholder="เช่น MB-AB12CD หรือ 0812345678"
                                   class="min-h-[44px] min-w-0 flex-1 rounded-xl border-line bg-white/70 text-sm">
                            <button type="submit" class="btn-primary shrink-0 px-4 text-sm" wire:loading.attr="disabled" wire:target="addMember">เพิ่ม</button>
                        </div>
                        <p class="text-xs text-muted">ต้องพิมพ์ให้ตรงทั้งหมด ระบบไม่แสดงรายชื่อสมาชิกคนอื่น</p>
                    </form>

                    @if ($this->groups->isNotEmpty())
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">เพิ่มทั้งกลุ่ม</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->groups as $g)
                                    @php($gc = $g->palette())
                                    <button type="button" wire:click="addGroup({{ $g->id }})" wire:key="grp-{{ $g->id }}"
                                            class="btn-grad-soft min-h-[40px] text-sm"
                                            style="--g-soft: {{ $gc['soft'] }}; --g-base: {{ $gc['base'] }}; --g-ink: {{ $gc['ink'] }}">
                                        @svg('lucide-users', 'h-4 w-4')
                                        {{ $g->name }} <span class="opacity-70">· {{ $g->members->count() }} คน</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($this->teamShortcuts->isNotEmpty())
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">ลูกทีมของคุณ</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->teamShortcuts as $m)
                                    <button type="button" wire:click="addFromTeam({{ $m->id }})" wire:key="team-{{ $m->id }}"
                                            class="inline-flex min-h-[40px] items-center gap-2 rounded-full border border-line bg-white/60 py-1 pl-1 pr-3 text-sm hover:bg-mist">
                                        <x-avatar :user="$m->user" size="h-7 w-7" />
                                        {{ $m->user->displayName() }}
                                        @svg('lucide-plus', 'h-3.5 w-3.5 text-muted')
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </section>

        {{-- 4. Trainer (เฉพาะ Trainee) --}}
        @if ($trainee)
            <section class="card overflow-hidden">
                <div class="card-head">
                    <h2 class="font-display text-[17px] font-bold text-grad">4. Trainer</h2>
                    <p class="mt-0.5 text-xs text-muted">แสดงเฉพาะ Trainer ที่ว่างครบทุกชั่วโมงของช่วงที่เลือก</p>
                </div>

                <div class="p-4 sm:p-5">
                    @if (! $start)
                        <p class="text-sm text-muted">เลือกเวลาเริ่มก่อน</p>
                    @else
                        <div class="space-y-2">
                            @forelse ($this->trainers as $t)
                                <button type="button" wire:click="chooseTrainer({{ $t->id }})" wire:key="tr-{{ $t->id }}"
                                        aria-pressed="{{ $trainerId === $t->id ? 'true' : 'false' }}"
                                        class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition
                                               {{ $trainerId === $t->id ? 'border-brand-dark bg-brand-light/30' : 'border-line bg-white/60 hover:bg-mist' }}">
                                    <x-avatar :user="$t->user" size="h-9 w-9" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-ink">{{ $t->user->displayName() }}</span>
                                        @if ($t->specialties)
                                            <span class="block truncate text-xs text-muted">{{ implode(' · ', (array) $t->specialties) }}</span>
                                        @endif
                                    </span>
                                    @if ($trainerId === $t->id)
                                        @svg('lucide-circle-check', 'h-5 w-5 text-brand-deep')
                                    @endif
                                </button>
                            @empty
                                <p class="text-sm text-muted">ไม่มี Trainer ว่างครบช่วงนี้ ลองเลือกเวลาอื่น</p>
                            @endforelse

                            @if ($this->mayGoWithoutTrainer)
                                <button type="button" wire:click="chooseTrainer(null)"
                                        aria-pressed="{{ $noTrainer ? 'true' : 'false' }}"
                                        class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition
                                               {{ $noTrainer ? 'border-brand-dark bg-brand-light/30' : 'border-dashed border-line bg-white/40 hover:bg-mist' }}">
                                    <span class="grid h-9 w-9 place-items-center rounded-full bg-mist text-muted">@svg('lucide-user-x', 'h-4 w-4')</span>
                                    <span class="flex-1 text-sm font-medium text-ink">เข้าใช้โดยไม่มี Trainer</span>
                                    @if ($noTrainer)
                                        @svg('lucide-circle-check', 'h-5 w-5 text-brand-deep')
                                    @endif
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- 5. ผู้ชำระเงิน --}}
        <section class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">{{ $trainee ? '5' : '4' }}. ผู้ชำระเงิน</h2>
                <p class="mt-0.5 text-xs text-muted">ผู้ชำระเงินจ่ายเต็มจำนวนคนเดียว และต้องเป็นผู้เข้าร่วม</p>
            </div>
            <div class="p-4 sm:p-5">
                @if ($this->participants->isEmpty())
                    <p class="text-sm text-muted">เพิ่มผู้เข้าร่วมก่อน</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->participants as $m)
                            <button type="button" wire:click="choosePayer({{ $m->id }})" wire:key="payer-{{ $m->id }}"
                                    aria-pressed="{{ $payerId === $m->id ? 'true' : 'false' }}"
                                    class="min-h-[40px] rounded-full border px-4 text-sm font-medium transition
                                           {{ $payerId === $m->id ? 'border-brand-dark bg-brand-dark text-white' : 'border-line bg-white/60 text-ink hover:bg-mist' }}">
                                {{ $m->user->displayName() }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- สรุปและยืนยัน ตรึงไว้ด้านล่าง เหนือแถบเมนูมือถือ --}}
    <div class="fixed inset-x-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 px-4 sm:bottom-6">
        <div class="mx-auto max-w-3xl rounded-2xl border border-line bg-white/95 p-3 shadow-soft backdrop-blur sm:p-4">
            @if ($error)
                <p class="mb-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $error }}</p>
            @endif
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] text-muted">
                        @if ($start)
                            {{ $selected->locale('th')->isoFormat('ddd D MMM') }} · {{ $start }}–{{ $this->startAt->addHours($hours)->format('H:i') }} · {{ count($memberIds) }} คน
                        @else
                            เลือกเวลาเริ่มเพื่อดูสรุป
                        @endif
                    </p>
                    <p class="font-display text-xl font-bold text-ink">
                        ฿{{ number_format((float) $this->amount, 0) }}
                        <span class="text-xs font-normal text-muted">{{ $hours }} ชม. × ฿{{ number_format((float) $branch->hourly_rate, 0) }}</span>
                    </p>
                </div>
                @php($ready = $priced && $start && $payerId && count($memberIds) > 0 && (! $trainee || $trainerId || $noTrainer))
                <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                        @disabled(! $ready)
                        class="btn-grad shrink-0 px-5 py-3 text-[15px] font-semibold disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="submit">จองและไปชำระเงิน</span>
                    <span wire:loading wire:target="submit">กำลังจอง…</span>
                </button>
            </div>
        </div>
    </div>
</div>
