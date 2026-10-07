<div class="pb-16">
    @php($trainer = $mode === 'trainer')
    @php($bookRoute = $trainer ? 'trainer.book' : 'member.book')

    <x-page-hero :eyebrow="$trainer ? 'งานของฉัน' : 'การจองของฉัน'"
                 :title="$trainer ? 'งานที่ได้รับมอบหมาย' : 'การจองยิม'"
                 pattern="kettlebell" pattern-alt="rower">
        <x-slot:action>
            <a href="{{ route($bookRoute) }}" wire:navigate
               class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                @svg('lucide-plus', 'h-4 w-4', ['stroke-width' => '2'])
                {{ $trainer ? 'จองให้ลูกเทรน' : 'จองยิม' }}
            </a>
        </x-slot:action>
        @if ($this->awaitingPayment > 0)
            <x-slot:stats>
                <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-accent-light px-3 py-1 text-[13px] font-medium text-accent-ink">
                    @svg('lucide-clock', 'h-4 w-4') รอชำระเงิน {{ $this->awaitingPayment }} รายการ
                </p>
            </x-slot:stats>
        @endif
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-6 sm:px-6">
        <div class="inline-flex rounded-full border border-line bg-white/60 p-1" role="tablist">
            @foreach (['upcoming' => 'กำลังจะถึง', 'past' => 'ที่ผ่านมา'] as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        wire:click="setTab('{{ $key }}')"
                        class="min-h-[40px] rounded-full px-4 text-sm font-medium transition {{ $tab === $key ? 'bg-brand-dark text-white' : 'text-ink hover:bg-mist' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            @if ($this->reservations->isEmpty())
                <div class="px-5 py-12 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="stopwatch" class="h-7 w-7 text-brand-deep" />
                    </span>
                    <p class="mt-4 font-display text-lg font-bold text-ink">{{ $tab === 'upcoming' ? 'ยังไม่มีการจองที่กำลังจะถึง' : 'ยังไม่มีประวัติ' }}</p>
                    @if ($tab === 'upcoming')
                        <a href="{{ route($bookRoute) }}" wire:navigate class="btn-grad mt-4 inline-flex px-5 py-2.5 text-sm font-semibold">
                            {{ $trainer ? 'จองให้ลูกเทรน' : 'จองยิม' }}
                        </a>
                    @endif
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->reservations as $r)
                        <li wire:key="r-{{ $r->id }}">
                            <a href="{{ route('reservations.show', $r->reference) }}" wire:navigate
                               class="flex items-center gap-3 px-4 py-4 transition hover:bg-mist/50 sm:gap-5 sm:px-5">
                                <div class="w-14 shrink-0 text-center sm:w-16">
                                    <p class="text-[11px] leading-none text-muted">{{ $r->starts_at->locale('th')->isoFormat('ddd') }}</p>
                                    <p class="mt-1 font-display text-xl font-bold leading-none text-ink">{{ $r->starts_at->format('j') }}</p>
                                    <p class="mt-1 text-[11px] leading-none text-muted">{{ $r->starts_at->locale('th')->isoFormat('MMM') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-display text-[15px] font-bold text-ink">{{ $r->timeLabel() }} <span class="font-sans text-xs font-normal text-muted">· {{ $r->hours }} ชม.</span></p>
                                    <p class="mt-0.5 truncate text-[13px] text-muted">
                                        @if ($trainer)
                                            {{ $r->participants->map(fn ($m) => $m->user->displayName())->join(', ') }}
                                        @else
                                            {{ $r->trainer?->user?->displayName() ?? 'ไม่มี Trainer' }} · {{ $r->participants->count() }} คน
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <x-status-chip :status="$r->status" />
                                    @if ($r->refund_status)
                                        <x-status-chip :status="$r->refund_status" />
                                    @endif
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
