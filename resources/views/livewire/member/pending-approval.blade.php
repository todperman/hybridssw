<div class="pb-16">
    @php($member = $this->member())
    @php($rejected = $member->status === \App\Enums\MemberStatus::Rejected)

    <x-page-hero :eyebrow="$member->branch?->name" :title="$this->headline()" pattern="stopwatch" pattern-alt="box"
                 :subtitle="$member->member_code">
        <x-slot:stats>
            <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-white/80">{{ $this->lead() }}</p>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">

        <div class="card overflow-hidden">
            <div class="card-head flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-[17px] font-bold text-grad">สถานะการสมัคร</h2>

                <span class="chip {{ $rejected ? 'bg-red-100 text-red-800' : 'bg-accent-light text-accent-ink' }}">
                    {{ $member->status->label() }}
                </span>
            </div>

            <dl class="divide-y divide-line/70 text-[13px]">
                @foreach ([
                    ['ชื่อ', $member->user->displayName()],
                    ['อีเมล', $member->user->email],
                    ['เบอร์โทร', $member->user->phone],
                    ['สาขา', $member->branch?->name],
                    ['สมัครเมื่อ', $member->created_at?->locale('th')->isoFormat('D MMM YYYY · HH:mm')],
                ] as [$label, $value])
                    <div class="flex items-start justify-between gap-4 px-5 py-3">
                        <dt class="shrink-0 text-muted">{{ $label }}</dt>
                        <dd class="min-w-0 flex-1 text-end {{ $value ? 'text-ink' : 'text-muted/60' }}">{{ $value ?: 'ไม่ได้กรอก' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($member->review_note)
                <div class="border-t border-line bg-accent-light/40 px-5 py-4">
                    <p class="text-[12px] font-semibold uppercase tracking-[0.12em] text-accent-ink">บันทึกจากแอดมิน</p>
                    <p class="mt-1 text-sm leading-relaxed text-accent-ink/90">{{ $member->review_note }}</p>
                </div>
            @endif
        </div>

        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">ระหว่างรอ</h2>
            </div>

            <ul class="divide-y divide-line/70 text-sm">
                @foreach ([
                    [false, 'จองยิม'],
                    [true, 'แก้ไขข้อมูลโปรไฟล์ของคุณ'],
                ] as [$allowed, $text])
                    <li class="flex items-center gap-3 px-5 py-3">
                        @if ($allowed)
                            @svg('lucide-check', 'h-4 w-4 shrink-0 text-brand-deep', ['stroke-width' => '2.4'])
                            <span class="text-ink">{{ $text }}</span>
                        @else
                            @svg('lucide-x', 'h-4 w-4 shrink-0 text-muted/60', ['stroke-width' => '2.4'])
                            <span class="text-muted">{{ $text }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                <a href="{{ route('profile') }}" wire:navigate class="btn-grad px-5 text-[13px]">แก้ไขโปรไฟล์</a>

                {{-- รีเฟรชเพื่อดึงสถานะล่าสุด เผื่อแอดมินเพิ่งกดอนุมัติ ถ้าผ่านแล้วจะถูกพาไปหน้าจองเอง --}}
                <button type="button" onclick="window.location.reload()" class="btn-ghost text-[13px]">
                    @svg('lucide-refresh-cw', 'h-4 w-4', ['stroke-width' => '2'])
                    เช็กสถานะอีกครั้ง
                </button>
            </div>
        </div>
    </div>
</div>
