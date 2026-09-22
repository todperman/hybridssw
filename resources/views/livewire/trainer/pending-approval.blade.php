<div class="pb-16">
    @php($trainer = $this->trainer())
    @php($rejected = $trainer->status === \App\Enums\TrainerStatus::Rejected)
    @php($suspended = $trainer->status === \App\Enums\TrainerStatus::Suspended)

    <x-page-hero :eyebrow="$trainer->branch?->name" :title="$this->headline()" pattern="stopwatch" pattern-alt="box"
                 :subtitle="$trainer->code">
        <x-slot:stats>
            <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-white/80">{{ $this->lead() }}</p>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">

        {{-- สถานะปัจจุบันและเหตุผล (ถ้ามี) --}}
        <div class="card overflow-hidden">
            <div class="card-head flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-[17px] font-bold text-grad">สถานะใบสมัคร</h2>

                <span class="chip {{ $rejected || $suspended ? 'bg-red-100 text-red-800' : 'bg-accent-light text-accent-ink' }}">
                    {{ $trainer->status->label() }}
                </span>
            </div>

            <dl class="divide-y divide-line/70 text-[13px]">
                @foreach ([
                    ['ชื่อ', $trainer->user->displayName()],
                    ['อีเมล', $trainer->user->email],
                    ['ประเภท', $trainer->type->label()],
                    ['สาขา', $trainer->branch?->name],
                    ['สมัครเมื่อ', $trainer->created_at?->locale('th')->isoFormat('D MMM YYYY · HH:mm')],
                ] as [$label, $value])
                    <div class="flex items-start justify-between gap-4 px-5 py-3">
                        <dt class="shrink-0 text-muted">{{ $label }}</dt>
                        <dd class="min-w-0 flex-1 text-end {{ $value ? 'text-ink' : 'text-muted/60' }}">{{ $value ?: 'ไม่ได้กรอก' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($trainer->review_note)
                <div class="border-t border-line bg-accent-light/40 px-5 py-4">
                    <p class="text-[12px] font-semibold uppercase tracking-[0.12em] text-accent-ink">บันทึกจากแอดมิน</p>
                    <p class="mt-1 text-sm leading-relaxed text-accent-ink/90">{{ $trainer->review_note }}</p>
                </div>
            @endif
        </div>

        {{-- บอกให้ชัดว่าตอนนี้ทำอะไรได้ ทำอะไรไม่ได้ ไม่ใช่ปล่อยให้เดาเอง --}}
        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">ระหว่างรอ</h2>
            </div>

            <ul class="divide-y divide-line/70 text-sm">
                @foreach ([
                    [false, 'จองรอบให้ลูกทีม'],
                    [false, 'ชวนลูกทีมเข้าทีม'],
                    [true, 'แก้ไขข้อมูลโปรไฟล์ของคุณ'],
                ] as [$allowed, $text])
                    <li class="flex items-center gap-3 px-5 py-3">
                        @if ($allowed)
                            <x-lucide-check class="h-4 w-4 shrink-0 text-brand-deep" stroke-width="2.4" />
                            <span class="text-ink">{{ $text }}</span>
                        @else
                            <x-lucide-x class="h-4 w-4 shrink-0 text-muted/60" stroke-width="2.4" />
                            <span class="text-muted">{{ $text }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                <a href="{{ route('profile') }}" wire:navigate class="btn-grad px-5 text-[13px]">แก้ไขโปรไฟล์</a>

                {{-- รีเฟรชหน้าเพื่อดึงสถานะล่าสุด เผื่อแอดมินเพิ่งกดอนุมัติ --}}
                <button type="button" onclick="window.location.reload()" class="btn-ghost text-[13px]">
                    <x-lucide-refresh-cw class="h-4 w-4" stroke-width="2" />
                    เช็กสถานะอีกครั้ง
                </button>
            </div>
        </div>
    </div>
</div>
