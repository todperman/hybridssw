<div class="pb-16">
    @php($member = $this->member())

    <x-page-hero :eyebrow="$member->member_code" title="คิวของฉัน" pattern="stopwatch" pattern-alt="box"
                 :subtitle="$member->branch->name">
        <x-slot:action>
            <x-avatar :user="$member->user" size="h-12 w-12" text="text-lg" class="ring-2 ring-white/30" />
        </x-slot:action>

        <x-slot:stats>
            <dl class="mt-5 grid max-w-md grid-cols-3 gap-2 sm:gap-3">
                @foreach ([
                    ['เครดิตคงเหลือ', $member->availableCredits()],
                    ['คิวที่จะถึง', $this->upcoming->count()],
                    ['ไม่มาตามนัด', $member->no_show_count],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-2.5 py-2 backdrop-blur-sm sm:px-3 sm:py-2.5">
                        <dt class="text-[11px] leading-tight text-white/65">{{ $label }}</dt>
                        <dd class="mt-0.5 font-display text-xl font-bold leading-none text-paper sm:text-2xl">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($member->isSuspended())
                <p class="mt-4 max-w-md rounded-xl border border-red-300/40 bg-red-500/15 px-4 py-2.5 text-[13px] text-red-100">
                    ถูกระงับสิทธิ์จองถึง {{ $member->suspended_until?->format('d/m/Y') }}
                </p>
            @endif
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-5xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">

        {{-- คิวที่กำลังจะถึง --}}
        <div class="card overflow-hidden">
            <div class="card-head flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-[17px] font-bold text-grad">คิวที่กำลังจะถึง</h2>
                    <p class="mt-0.5 text-[13px] text-muted">เทรนเนอร์เป็นคนจองให้ คุณยืนยันหรือยกเลิกได้ที่นี่</p>
                </div>

                @if ($this->upcoming->isNotEmpty())
                    <span class="chip bg-brand-light/40 text-brand-dark">{{ $this->upcoming->count() }} คิว</span>
                @endif
            </div>

            @if ($this->upcoming->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6 sm:py-14">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-mist">
                        <x-station-icon name="stopwatch" class="h-7 w-7 text-brand-deep" />
                    </span>
                    <p class="mt-4 font-display text-lg font-bold text-ink">ยังไม่มีคิว</p>
                    <p class="mt-1 text-sm text-muted">ติดต่อเทรนเนอร์ของคุณเพื่อจองให้</p>
                </div>
            @else
                <div class="grid gap-3 p-3 sm:grid-cols-2 sm:p-4">
                    @foreach ($this->upcoming as $booking)
                        @php($session = $booking->workoutSession)
                        @php($waitlisted = $booking->status === \App\Enums\BookingStatus::Waitlisted)
                        @php($needsConfirm = (bool) $booking->confirm_deadline_at)

                        <div wire:key="upcoming-{{ $booking->id }}"
                             class="flex flex-col rounded-lg2 border bg-white/70 p-3.5 transition hover:shadow-soft sm:p-4
                                {{ $needsConfirm ? 'border-accent' : 'border-line' }}">

                            <div class="flex items-start gap-3">
                                {{-- บล็อกวันที่ อ่านง่ายกว่าข้อความยาว --}}
                                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl sm:h-14 sm:w-14 {{ $waitlisted ? 'bg-accent-light' : 'bg-mist' }}">
                                    <span class="text-[10px] uppercase leading-none {{ $waitlisted ? 'text-accent-ink' : 'text-brand-deep' }}">
                                        {{ $session->starts_at->locale('th')->isoFormat('MMM') }}
                                    </span>
                                    <span class="font-display text-lg font-bold leading-tight sm:text-xl {{ $waitlisted ? 'text-accent-ink' : 'text-brand-dark' }}">
                                        {{ $session->starts_at->format('j') }}
                                    </span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-display text-[16px] font-bold text-ink sm:text-[17px]">{{ $session->timeLabel() }}</p>
                                    <p class="mt-0.5 text-[12px] text-muted">
                                        {{ $session->starts_at->locale('th')->isoFormat('dddd D MMM') }}
                                    </p>

                                    <div class="mt-2 flex items-center gap-2">
                                        <x-avatar :user="$booking->trainer->user" size="h-6 w-6" text="text-[10px]" />
                                        <span class="truncate text-[12px] text-muted">{{ $booking->trainer->user->name }}</span>
                                    </div>
                                </div>

                                <span class="chip shrink-0 text-[11px] sm:text-xs {{ $waitlisted ? 'bg-accent-light text-accent-ink' : 'bg-brand-light/40 text-brand-dark' }}">
                                    {{ $booking->status->label() }}@if ($waitlisted) · คิวที่ {{ $booking->waitlist_position }} @endif
                                </span>
                            </div>

                            <p class="mt-2.5 font-mono text-[11px] text-muted/60">{{ $booking->reference }}</p>

                            {{-- ได้เลื่อนขึ้นจากคิวสำรองแล้ว ต้องกดยืนยันไม่งั้นที่นั่งถูกปล่อย --}}
                            @if ($needsConfirm)
                                <div class="mt-3 rounded-xl border border-accent bg-accent-light/60 px-3.5 py-3 sm:px-4">
                                    <p class="text-[13px] text-accent-ink">
                                        คุณได้เลื่อนขึ้นจากคิวสำรองแล้ว ยืนยันภายใน
                                        <span class="font-semibold">{{ $booking->confirm_deadline_at->format('H:i') }}</span>
                                        ไม่งั้นที่นั่งจะถูกปล่อย
                                    </p>
                                    <button wire:click="confirm({{ $booking->id }})" class="btn-primary mt-2.5 w-full">ยืนยันสิทธิ์</button>
                                </div>
                            @endif

                            @if ($booking->status->isCancellable())
                                <div class="mt-3 flex justify-center border-t border-line pt-3">
                                    <button type="button" class="btn-ghost text-[13px]"
                                            @click="$store.confirm.ask({
                                                title: 'ยกเลิกคิวนี้',
                                                body: @js($session->starts_at->format('d/m/Y').' · '.$session->timeLabel()),
                                                notes: @js($booking->wouldBeLateCancellation()
                                                    ? ['เลยกำหนดยกเลิกฟรีแล้ว เครดิตจะถูกหัก 1 ครั้ง', 'ที่นั่งจะถูกปล่อยให้คนในคิวสำรอง']
                                                    : ['ยกเลิกทันเวลา เครดิตจะถูกคืนให้เต็มจำนวน', 'ที่นั่งจะถูกปล่อยให้คนในคิวสำรอง']),
                                                tone: 'danger',
                                                confirmLabel: 'ยกเลิกคิว',
                                                cancelLabel: 'เก็บไว้ก่อน',
                                                action: () => $wire.cancel({{ $booking->id }}),
                                            })">
                                        ยกเลิกคิว
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ประวัติ --}}
        <div class="card overflow-hidden">
            <h2 class="card-head font-display text-[17px] font-bold text-grad">ประวัติล่าสุด</h2>

            @if ($this->history->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-muted">ยังไม่มีประวัติ</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->history as $booking)
                        @php($good = in_array($booking->status, [\App\Enums\BookingStatus::Completed, \App\Enums\BookingStatus::CheckedIn]))
                        @php($bad = $booking->status === \App\Enums\BookingStatus::NoShow)

                        <li class="flex items-center justify-between gap-2.5 px-4 py-3 sm:gap-3 sm:px-5">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $good ? 'bg-brand-deep' : ($bad ? 'bg-red-500' : 'bg-line') }}"></span>

                                <x-avatar :user="$booking->trainer->user" size="h-8 w-8" text="text-[11px]" />

                                <span class="min-w-0">
                                    <span class="block truncate text-[14px] text-ink">{{ $booking->workoutSession->starts_at->format('d/m/Y H:i') }}</span>
                                    <span class="block truncate text-[12px] text-muted">{{ $booking->trainer->user->name }}</span>
                                </span>
                            </span>

                            <span class="chip shrink-0 {{ $good ? 'bg-brand-light/40 text-brand-dark' : ($bad ? 'bg-red-100 text-red-800' : 'bg-line/60 text-muted') }}">
                                {{ $booking->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
