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
                        <span class="text-sm text-muted">ยอดชำระ ({{ $r->priceLabel() }})</span>
                        <span class="font-display text-2xl font-bold text-ink">฿{{ number_format((float) $r->amount, 2) }}</span>
                    </div>
                    <p class="text-sm text-muted">
                        ผู้ชำระเงิน: <span class="font-medium text-ink">{{ $r->payer->user->displayName() }}</span>
                        · ต้องชำระภายใน {{ $r->hold_expires_at->format('H:i') }} น. ไม่งั้นระบบจะปล่อยเวลานี้ให้คนอื่นจอง
                    </p>

                    @if ($this->isPayer)
                        @if ($this->omise)
                            @php($qr = $this->activeQr)
                            @php($methods = $this->omiseMethods)

                            @if ($this->omise->isTestMode())
                                <p class="rounded-lg bg-accent-light px-3 py-2 text-xs font-medium text-accent-ink">โหมดทดสอบ ไม่มีการตัดเงินจริง</p>
                            @endif

                            @php($catalog = \App\Services\Payments\Omise\PaymentMethods::all())
                            @php($waiting = $this->pendingRedirect)

                            @if ($waiting)
                                <div class="flex items-start gap-3 rounded-xl border border-accent/40 bg-accent-light/60 px-4 py-3 text-sm text-accent-ink" wire:key="waiting-{{ $waiting->id }}">
                                    <span class="mt-0.5 h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-accent-ink/30 border-t-accent-ink"></span>
                                    <span>กำลังรอผลจาก {{ $waiting->methodLabel() }} ถ้ายืนยันในแอปแล้ว หน้านี้จะอัปเดตเองภายในไม่กี่วินาที ยังไม่ได้ยืนยันก็เลือกช่องทางใหม่ได้</span>
                                </div>
                            @endif

                            @if (count($methods) > 1)
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" role="tablist" aria-label="ช่องทางชำระเงิน">
                                    @foreach ($methods as $m => $sources)
                                        @php($meta = $catalog[$m])
                                        <button type="button" role="tab" aria-selected="{{ $method === $m ? 'true' : 'false' }}"
                                                wire:click="setMethod('{{ $m }}')" wire:key="pm-{{ $m }}"
                                                class="flex min-h-[76px] flex-col items-start justify-between gap-2 rounded-2xl border p-3 text-left transition
                                                       {{ $method === $m ? 'border-brand-dark bg-brand-light/25 ring-2 ring-brand-dark/60' : 'border-line bg-white/70 hover:bg-mist' }}">
                                            <span class="flex h-7 items-center gap-1">
                                                @foreach (array_slice($meta['logos'], 0, 3) as $logo)
                                                    <img src="{{ asset('img/payments/'.$logo) }}" alt="" class="h-7 w-auto max-w-[44px] rounded-md object-contain" loading="lazy">
                                                @endforeach
                                                @if (count($meta['logos']) > 3)
                                                    <span class="text-[11px] text-muted">+{{ count($meta['logos']) - 3 }}</span>
                                                @endif
                                            </span>
                                            <span class="block text-[13px] font-medium leading-tight text-ink">{{ $meta['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if (! $error && $this->lastFailure)
                                <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">ครั้งก่อนไม่สำเร็จ: {{ $this->lastFailure->failure_message ?: 'กรุณาลองใหม่' }}</p>
                            @endif

                            @if ($method === 'promptpay')
                                @if ($qr)
                                    <div class="flex flex-col items-center gap-3 rounded-xl border border-line bg-white p-4 text-center">
                                        <img src="{{ $qr->payload['qr'] }}" alt="QR พร้อมเพย์ ยอด {{ number_format((float) $r->amount, 2) }} บาท สำหรับ {{ $r->reference }}"
                                             class="h-60 w-60 max-w-full" width="240" height="240">
                                        <p class="font-display text-xl font-bold text-ink">฿{{ number_format((float) $r->amount, 2) }}</p>
                                        <a href="{{ $qr->payload['qr'] }}" target="_blank" rel="noopener" download="promptpay-{{ $r->reference }}"
                                           class="btn-ghost inline-flex items-center gap-2 text-sm">
                                            @svg('lucide-download', 'h-4 w-4') บันทึกรูป QR
                                        </a>
                                        <ol class="w-full space-y-1 text-left text-[13px] text-muted">
                                            <li>1. เปิดแอปธนาคาร เลือกสแกนจ่าย</li>
                                            <li>2. สแกน QR นี้ หรือบันทึกรูปแล้วเลือกจากอัลบั้ม</li>
                                            <li>3. ชำระแล้วรอสักครู่ หน้านี้จะยืนยันการจองเอง</li>
                                        </ol>
                                    </div>
                                @else
                                    <button type="button" wire:click="startPayment" wire:loading.attr="disabled"
                                            class="btn-grad w-full py-3 text-[15px] font-semibold">
                                        <span wire:loading.remove wire:target="startPayment">สร้าง QR พร้อมเพย์</span>
                                        <span wire:loading wire:target="startPayment">กำลังสร้าง QR…</span>
                                    </button>
                                @endif
                            @endif

                            @if ($method === 'card')
                                <div x-data="{
                                        busy: false,
                                        open() {
                                            this.busy = true;
                                            const go = () => {
                                                OmiseCard.configure({ publicKey: @js($this->omise->publicKey()) });
                                                OmiseCard.open({
                                                    amount: {{ $this->omise->satang($r->amount) }},
                                                    currency: 'THB',
                                                    defaultPaymentMethod: 'credit_card',
                                                    otherPaymentMethods: [],
                                                    frameLabel: 'Hybrid SSW',
                                                    frameDescription: @js('การจอง '.$r->reference),
                                                    submitLabel: 'ชำระเงิน',
                                                    locale: 'th',
                                                    onCreateTokenSuccess: (token) => $wire.payByCard(token).finally(() => this.busy = false),
                                                    onFormClosed: () => this.busy = false,
                                                });
                                            };
                                            if (window.OmiseCard) return go();
                                            const script = document.createElement('script');
                                            script.src = 'https://cdn.omise.co/omise.js';
                                            script.onload = go;
                                            script.onerror = () => { this.busy = false; alert('โหลดหน้าชำระด้วยบัตรไม่ได้ ตรวจอินเทอร์เน็ตแล้วลองใหม่'); };
                                            document.head.appendChild(script);
                                        },
                                     }" class="space-y-3">
                                    @if ($qr)
                                        <p class="rounded-lg bg-accent-light px-3 py-2 text-sm text-accent-ink">ถ้าสแกน QR จ่ายไปแล้ว ไม่ต้องจ่ายด้วยบัตรซ้ำ รอสักครู่ให้ระบบยืนยัน</p>
                                    @endif
                                    <button type="button" @click="open()" :disabled="busy" wire:loading.attr="disabled" wire:target="payByCard"
                                            class="btn-grad w-full py-3 text-[15px] font-semibold disabled:opacity-60">
                                        <span x-show="! busy">ชำระด้วยบัตร ฿{{ number_format((float) $r->amount, 2) }}</span>
                                        <span x-show="busy" x-cloak>กำลังดำเนินการ…</span>
                                    </button>
                                    <p class="flex items-start gap-2 text-xs leading-relaxed text-muted">
                                        @svg('lucide-shield-check', 'mt-0.5 h-4 w-4 shrink-0 text-brand-deep')
                                        ข้อมูลบัตรส่งตรงถึง Omise ผู้ให้บริการชำระเงินที่ได้มาตรฐาน PCI DSS ไม่ผ่านและไม่ถูกเก็บในระบบของยิม รองรับ Visa, Mastercard และ JCB
                                    </p>
                                </div>
                            @endif
                            @if ($method === 'mobile_banking')
                                <div class="space-y-2">
                                    <p class="text-sm text-muted">เลือกธนาคาร แล้วแอปธนาคารจะเปิดขึ้นมาให้ยืนยันยอด ฿{{ number_format((float) $r->amount, 2) }}</p>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ($methods['mobile_banking'] as $bankSource)
                                            @php($bank = \App\Services\Payments\Omise\PaymentMethods::BANKS[$bankSource])
                                            <button type="button" wire:click="payRedirect('{{ $bankSource }}')" wire:key="bank-{{ $bankSource }}"
                                                    wire:loading.attr="disabled" wire:target="payRedirect"
                                                    class="flex min-h-[56px] items-center gap-3 rounded-xl border border-line bg-white/80 px-3 text-left transition hover:border-brand/50 hover:bg-mist disabled:opacity-60">
                                                <img src="{{ asset('img/payments/'.$bank['logo']) }}" alt="" class="h-9 w-9 rounded-lg object-contain">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-medium text-ink">{{ $bank['label'] }}</span>
                                                    <span class="block text-xs text-muted">ธนาคาร{{ $bank['bank'] }}</span>
                                                </span>
                                                @svg('lucide-chevron-right', 'h-4 w-4 shrink-0 text-muted')
                                            </button>
                                        @endforeach
                                    </div>
                                    <p class="text-xs text-muted">ใช้ได้บนมือถือที่ติดตั้งแอปของธนาคารนั้น</p>
                                </div>
                            @endif

                            @if ($method === 'rabbit_linepay')
                                <div class="space-y-3">
                                    <button type="button" wire:click="payRedirect('rabbit_linepay')" wire:loading.attr="disabled" wire:target="payRedirect"
                                            class="flex w-full items-center justify-center gap-3 rounded-xl bg-[#06C755] py-3 text-[15px] font-semibold text-white transition hover:brightness-95 disabled:opacity-60">
                                        <img src="{{ asset('img/payments/rabbit-linepay.svg') }}" alt="" class="h-7 w-7 rounded-md bg-white object-contain p-0.5">
                                        <span wire:loading.remove wire:target="payRedirect">ชำระด้วย Rabbit LINE Pay ฿{{ number_format((float) $r->amount, 2) }}</span>
                                        <span wire:loading wire:target="payRedirect">กำลังไปที่ LINE…</span>
                                    </button>
                                    <p class="text-xs text-muted">บนมือถือจะเปิดแอป LINE ให้ยืนยัน บนคอมพิวเตอร์จะได้ QR ให้สแกนด้วย LINE ต้องยืนยันภายใน 20 นาที</p>
                                </div>
                            @endif

                            @if ($method === 'truemoney')
                                <form wire:submit="payRedirect('truemoney')" class="space-y-3">
                                    <label for="walletPhone" class="text-sm font-medium text-ink">เบอร์ที่ผูกกับ TrueMoney Wallet</label>
                                    <input id="walletPhone" type="tel" inputmode="numeric" wire:model="walletPhone" maxlength="12" autocomplete="tel"
                                           placeholder="0812345678" class="min-h-[44px] w-full rounded-xl border-line bg-white/70 text-sm">
                                    <button type="submit" wire:loading.attr="disabled" wire:target="payRedirect"
                                            class="flex w-full items-center justify-center gap-3 rounded-xl bg-[#F0612A] py-3 text-[15px] font-semibold text-white transition hover:brightness-95 disabled:opacity-60">
                                        <img src="{{ asset('img/payments/truemoney.svg') }}" alt="" class="h-7 w-7 rounded-md bg-white object-contain p-0.5">
                                        <span wire:loading.remove wire:target="payRedirect">ชำระด้วย TrueMoney ฿{{ number_format((float) $r->amount, 2) }}</span>
                                        <span wire:loading wire:target="payRedirect">กำลังไปหน้ายืนยัน…</span>
                                    </button>
                                    <p class="text-xs text-muted">ระบบจะส่ง OTP ไปที่เบอร์นี้เพื่อยืนยันยอด</p>
                                </form>
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
                    <dd class="text-right font-medium text-ink">
                        ฿{{ number_format((float) $r->amount, 2) }}
                        <span class="block text-xs font-normal text-muted">{{ $r->priceLabel() }}</span>
                        @if ((float) $r->discount_amount > 0)
                            <span class="block text-xs font-normal text-emerald-700">ใช้โค้ด {{ $r->coupon?->code ?? $r->pricing['coupon'] ?? '' }} ลด ฿{{ number_format((float) $r->discount_amount, 0) }}</span>
                        @endif
                    </dd>
                </div>
                @if ($paid = $r->payments->firstWhere('status', \App\Enums\PaymentStatus::Succeeded))
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-muted">ชำระด้วย</dt>
                        <dd class="text-right text-ink">{{ $paid->methodLabel() }} · {{ $paid->paid_at?->locale('th')->isoFormat('D MMM HH:mm') }}</dd>
                    </div>
                @endif
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
