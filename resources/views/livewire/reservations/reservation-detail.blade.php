<div class="pb-16">
    @php($r = $this->reservation)
    @php($pending = $r->status === \App\Enums\ReservationStatus::PendingPayment)
    @php($confirmed = $r->status === \App\Enums\ReservationStatus::Confirmed)
    @php($pendingRequests = $r->requests->where('status', \App\Enums\RequestStatus::Pending))
    @php($backRoute = auth()->user()->trainer ? 'trainer.jobs' : 'member.reservations')

    <x-page-hero eyebrow="การจอง {{ $r->reference }}" :title="$r->dateLabel()" :subtitle="$r->timeLabel().' · '.$r->hours.' ชม. · '.$r->branch->name"
                 pattern="stopwatch" pattern-alt="dumbbell">
        <x-slot:action>
            @if (Route::has($backRoute))
                <a href="{{ route($backRoute) }}" wire:navigate
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 text-sm font-medium text-paper backdrop-blur-sm transition hover:bg-white/20">
                    @svg('lucide-arrow-left', 'h-4 w-4') รายการจอง
                </a>
            @endif
        </x-slot:action>
        <x-slot:stats>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-status-chip :status="$r->status" class="text-sm" />
                @if ($r->refund_status)
                    <x-status-chip :status="$r->refund_status" class="text-sm" />
                @endif
            </div>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-4 px-4 pt-6 sm:px-6"
         @if ($pending) wire:poll.5s="refreshStatus" @endif>

        @if ($flash)
            <div class="card border-emerald-300 bg-emerald-50 px-5 py-3 text-sm text-emerald-800" role="status">{{ $flash }}</div>
        @endif
        @if ($error)
            <div class="card border-red-300 bg-red-50 px-5 py-3 text-sm text-red-700" role="alert">{{ $error }}</div>
        @endif

        {{-- รอชำระเงิน --}}
        @if ($pending)
            <section class="card overflow-hidden">
                <div class="card-head flex items-center justify-between gap-3">
                    <h2 class="font-display text-[17px] font-bold text-grad">ชำระเงิน</h2>
                    <span x-data="{
                              end: {{ $r->hold_expires_at->getTimestampMs() }},
                              left: '',
                              tick() {
                                  const s = Math.max(0, Math.floor((this.end - Date.now()) / 1000));
                                  this.left = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
                              },
                          }"
                          x-init="tick(); setInterval(() => tick(), 1000)"
                          class="chip bg-accent-light font-display text-sm tabular-nums text-accent-ink">
                        เหลือ <span class="ml-1" x-text="left"></span>
                    </span>
                </div>
                <div class="space-y-4 p-4 sm:p-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm text-muted">ยอดชำระ ({{ $r->hours }} ชม. × ฿{{ number_format((float) $r->hourly_rate, 0) }})</span>
                        <span class="font-display text-2xl font-bold text-ink">฿{{ number_format((float) $r->amount, 2) }}</span>
                    </div>
                    <p class="text-sm text-muted">
                        ผู้ชำระเงิน: <span class="font-medium text-ink">{{ $r->payer->user->displayName() }}</span>
                        · ต้องชำระภายใน {{ $r->hold_expires_at->format('H:i') }} น. ไม่งั้นระบบจะปล่อยเวลานี้ให้คนอื่นจอง
                    </p>

                    @if ($this->isPayer)
                        @if ($this->onlinePayment)
                            @if ($checkout && ! empty($checkout['qr']))
                                <div class="flex flex-col items-center gap-2 rounded-xl border border-line bg-white p-4">
                                    <img src="{{ $checkout['qr'] }}" alt="QR พร้อมเพย์สำหรับชำระ {{ $r->reference }}" class="h-56 w-56">
                                    <p class="text-sm text-muted">สแกนด้วยแอปธนาคาร หน้านี้จะอัปเดตเองเมื่อชำระสำเร็จ</p>
                                </div>
                            @else
                                <button type="button" wire:click="startPayment" wire:loading.attr="disabled"
                                        class="btn-grad w-full py-3 text-[15px] font-semibold">
                                    <span wire:loading.remove wire:target="startPayment">ชำระด้วย QR พร้อมเพย์</span>
                                    <span wire:loading wire:target="startPayment">กำลังสร้าง QR…</span>
                                </button>
                            @endif
                        @else
                            <div class="rounded-xl border border-line bg-mist/60 p-4 text-sm leading-relaxed text-ink">
                                @if (filled($r->branch->payment_instructions))
                                    {!! nl2br(e($r->branch->payment_instructions)) !!}
                                @else
                                    ติดต่อแอดมินเพื่อชำระเงิน
                                @endif
                                <p class="mt-2 text-muted">แจ้งเลขการจอง <span class="font-mono font-semibold text-ink">{{ $r->reference }}</span> เมื่อชำระแล้ว แอดมินจะยืนยันการจองให้</p>
                            </div>
                        @endif
                    @else
                        <p class="rounded-xl bg-mist/60 px-4 py-3 text-sm text-muted">รอ {{ $r->payer->user->displayName() }} ชำระเงิน หน้านี้จะอัปเดตเองเมื่อชำระสำเร็จ</p>
                    @endif
                </div>
            </section>
        @endif

        @if ($r->status === \App\Enums\ReservationStatus::Expired)
            <div class="card px-5 py-4 text-sm text-muted">ไม่ได้รับการชำระเงินภายในเวลาที่กำหนด เวลานี้ถูกปล่อยให้คนอื่นจองแล้ว</div>
        @endif

        @if ($r->status === \App\Enums\ReservationStatus::Cancelled)
            <div class="card px-5 py-4 text-sm text-ink">
                <p class="font-medium">การจองนี้ถูกยกเลิกโดยแอดมิน</p>
                @if ($r->cancellation_reason)
                    <p class="mt-1 text-muted">เหตุผล: {{ $r->cancellation_reason }}</p>
                @endif
                @foreach ($r->refunds as $refund)
                    <p class="mt-2 flex items-center gap-2">
                        คืนเงิน ฿{{ number_format((float) $refund->amount, 2) }}
                        <x-status-chip :status="$refund->status" />
                    </p>
                @endforeach
            </div>
        @endif

        {{-- รายละเอียด --}}
        <section class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">รายละเอียด</h2>
            </div>
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-muted">Trainer</dt>
                    <dd class="text-right font-medium text-ink">{{ $r->trainer?->user?->displayName() ?? 'เข้าใช้โดยไม่มี Trainer' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-muted">ผู้ชำระเงิน</dt>
                    <dd class="text-right font-medium text-ink">{{ $r->payer->user->displayName() }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-muted">ยอดเงิน</dt>
                    <dd class="text-right font-medium text-ink">฿{{ number_format((float) $r->amount, 2) }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-muted">จองโดย</dt>
                    <dd class="text-right text-ink">{{ $r->createdBy?->displayName() }} · {{ $r->created_at->locale('th')->isoFormat('D MMM HH:mm') }}</dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="text-muted">ผู้เข้าร่วม {{ $r->participants->count() }} คน</dt>
                    <dd class="mt-2 flex flex-wrap gap-2">
                        @foreach ($r->participants as $m)
                            <span class="inline-flex items-center gap-2 rounded-full bg-mist py-1 pl-1 pr-3 text-ink" wire:key="pp-{{ $m->id }}">
                                <x-avatar :user="$m->user" size="h-7 w-7" text="text-xs" />
                                {{ $m->user->displayName() }}
                            </span>
                        @endforeach
                    </dd>
                </div>
            </dl>
        </section>

        {{-- คำขอที่ส่งไปแล้ว --}}
        @if ($r->requests->isNotEmpty())
            <section class="card overflow-hidden">
                <div class="card-head">
                    <h2 class="font-display text-[17px] font-bold text-grad">คำขอ</h2>
                </div>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($r->requests->sortByDesc('id') as $req)
                        <li class="px-5 py-3" wire:key="req-{{ $req->id }}">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-ink">{{ $req->type->label() }}</span>
                                <x-status-chip :status="$req->status" />
                            </div>
                            @if ($req->new_starts_at)
                                <p class="mt-1 text-muted">ขอเลื่อนเป็น {{ $req->new_starts_at->locale('th')->isoFormat('ddd D MMM') }} {{ $req->new_starts_at->format('H:i') }}–{{ $req->new_ends_at->format('H:i') }}</p>
                            @endif
                            <p class="mt-1 text-muted">เหตุผล: {{ $req->reason }}</p>
                            @if ($req->review_note)
                                <p class="mt-1 text-muted">แอดมิน: {{ $req->review_note }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- เลื่อนวันเวลา --}}
        @if ($confirmed && $this->canManage && $r->starts_at->isFuture())
            @php($direct = $r->canRescheduleWithoutApproval() || auth()->user()->role?->canAccessAdminPanel())
            <section class="card overflow-hidden">
                <div class="card-head">
                    <h2 class="font-display text-[17px] font-bold text-grad">เลื่อนวันเวลา</h2>
                    <p class="mt-0.5 text-xs text-muted">
                        @if ($direct)
                            เลื่อนเองได้ถึง {{ $r->rescheduleDeadline()->subMinute()->locale('th')->isoFormat('ddd D MMM HH:mm') }} น. ราคาเท่าเดิม ใช้ Trainer และผู้เข้าร่วมเดิม
                        @else
                            เลยเวลาเลื่อนเองแล้ว ส่งคำขอพร้อมเหตุผลให้แอดมินพิจารณา
                        @endif
                    </p>
                </div>
                <div class="p-4 sm:p-5">
                    @if ($pendingRequests->contains('type', \App\Enums\RequestType::Reschedule))
                        <p class="text-sm text-muted">มีคำขอเลื่อนรอแอดมินพิจารณาอยู่</p>
                    @elseif (! $rescheduling)
                        <button type="button" wire:click="openReschedule" class="btn-ghost text-sm">
                            {{ $direct ? 'เลือกวันเวลาใหม่' : 'ขอเลื่อนการจอง' }}
                        </button>
                    @else
                        <div class="space-y-4">
                            <div class="scroll-quiet flex gap-1.5 overflow-x-auto pb-0.5">
                                @foreach ($this->rescheduleDays as $d)
                                    @php($ymd = $d->toDateString())
                                    <button type="button" wire:click="pickRescheduleDay('{{ $ymd }}')" wire:key="rd-{{ $ymd }}"
                                            class="flex min-w-[3.4rem] shrink-0 flex-col items-center rounded-2xl px-2 py-2 transition
                                                   {{ $ymd === $newDate ? 'bg-brand-dark text-white' : 'text-ink hover:bg-mist' }}">
                                        <span class="text-[11px] leading-none opacity-70">{{ $d->locale('th')->isoFormat('dd') }}</span>
                                        <span class="mt-1 font-display text-base font-bold leading-none">{{ $d->format('j') }}</span>
                                    </button>
                                @endforeach
                            </div>

                            @if (empty($this->rescheduleTimes))
                                <p class="text-sm text-muted">วันนี้ไม่มีช่วงที่ว่างครบ {{ $r->hours }} ชั่วโมง{{ $r->trainer ? 'สำหรับ Trainer คนเดิม' : '' }}</p>
                            @else
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                    @foreach ($this->rescheduleTimes as $t)
                                        @php($hm = $t->format('H:i'))
                                        <button type="button" wire:click="pickRescheduleStart('{{ $hm }}')" wire:key="rt-{{ $newDate }}-{{ $hm }}"
                                                class="min-h-[44px] rounded-xl border font-display text-[15px] font-bold transition
                                                       {{ $newStart === $hm ? 'border-brand-dark bg-brand-dark text-white' : 'border-line bg-white/60 text-ink hover:bg-mist' }}">
                                            {{ $hm }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <div>
                                <label for="rescheduleReason" class="text-sm font-medium text-ink">เหตุผล{{ $direct ? ' (ไม่บังคับ)' : '' }}</label>
                                <textarea id="rescheduleReason" wire:model="rescheduleReason" rows="2" maxlength="500"
                                          class="mt-1 w-full rounded-xl border-line bg-white/70 text-sm"></textarea>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" wire:click="submitReschedule" wire:loading.attr="disabled" @disabled(! $newStart)
                                        class="btn-grad px-5 py-2.5 text-sm font-semibold disabled:opacity-50">
                                    {{ $direct ? 'ยืนยันเลื่อน' : 'ส่งคำขอเลื่อน' }}
                                </button>
                                <button type="button" wire:click="$set('rescheduling', false)" class="btn-ghost text-sm">ยกเลิก</button>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- Trainer ขอยกเลิกการรับงาน --}}
        @if ($this->isAssignedTrainer && $r->status->holdsSlots() && $r->starts_at->isFuture())
            <section class="card overflow-hidden">
                <div class="card-head">
                    <h2 class="font-display text-[17px] font-bold text-grad">ไม่สะดวกรับงานนี้</h2>
                    <p class="mt-0.5 text-xs text-muted">ส่งคำขอให้แอดมินหา Trainer คนอื่นแทน งานยังเป็นของคุณจนกว่าแอดมินจะอนุมัติ</p>
                </div>
                <div class="p-4 sm:p-5">
                    @if ($pendingRequests->contains('type', \App\Enums\RequestType::TrainerWithdrawal))
                        <p class="text-sm text-muted">ส่งคำขอแล้ว รอแอดมินพิจารณา</p>
                    @elseif (! $withdrawing)
                        <button type="button" wire:click="$set('withdrawing', true)" class="btn-ghost text-sm">ขอยกเลิกการรับงาน</button>
                    @else
                        <form wire:submit="submitWithdrawal" class="space-y-3">
                            <label for="withdrawReason" class="text-sm font-medium text-ink">เหตุผล</label>
                            <textarea id="withdrawReason" wire:model="withdrawReason" rows="2" maxlength="500" required
                                      class="w-full rounded-xl border-line bg-white/70 text-sm"></textarea>
                            @error('withdrawReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2">
                                <button type="submit" class="btn-grad px-5 py-2.5 text-sm font-semibold">ส่งคำขอ</button>
                                <button type="button" wire:click="$set('withdrawing', false)" class="btn-ghost text-sm">ยกเลิก</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
</div>
