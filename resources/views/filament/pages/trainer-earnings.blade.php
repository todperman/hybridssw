<x-filament-panels::page>
    @php($stats = $this->stats)
    @php($monthStart = $this->monthStart)
    @php($branches = $this->branches())
    @php($statusClass = fn ($e) => 'is-'.$e->status->value)

    {{-- สรุป --}}
    <div class="te-stats">
        @foreach ([['วันนี้', $stats['today'], 'amount'], ['เดือนนี้', $stats['month'], 'amount'], ['ค้างจ่ายทั้งหมด', $stats['payable'], 'payable']] as [$label, $s, $key])
            <div class="te-stat">
                <p class="te-stat__label">{{ $label }}</p>
                <p class="te-stat__value">฿{{ number_format($s[$key]) }}</p>
                <p class="te-stat__meta">{{ $s['jobs'] }} งาน · {{ $s['hours'] }} ชม. · ลูกค้าจ่าย ฿{{ number_format($s['customer']) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ตัวกรอง --}}
    <div class="rc-toolbar">
        <div class="te-month">
            <button type="button" wire:click="shiftMonth(-1)" class="rc-nav" aria-label="เดือนก่อนหน้า">‹</button>
            <strong>{{ $monthStart->locale('th')->isoFormat('MMMM YYYY') }}</strong>
            <button type="button" wire:click="shiftMonth(1)" class="rc-nav" aria-label="เดือนถัดไป">›</button>
        </div>

        @if ($branches->count() > 1)
            <x-filament::input.wrapper class="rc-filter">
                <x-filament::input.select wire:model.live="branchId">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif

        <x-filament::input.wrapper class="rc-filter">
            <x-filament::input.select wire:model.live="trainerId">
                <option value="">เทรนเนอร์ทุกคน</option>
                @foreach ($this->trainers as $t)<option value="{{ $t->id }}">{{ $t->user?->name }}</option>@endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper class="rc-filter">
            <x-filament::input.select wire:model.live="mode">
                <option value="trainer">สรุปรายเทรนเนอร์</option>
                <option value="day">สรุปรายวัน</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <div class="rc-legend">
            <span><i class="is-paid"></i>จ่ายแล้ว</span>
            <span><i class="is-payable"></i>รอจ่าย</span>
            <span><i class="is-accrued"></i>รอถึงเวลา</span>
            <span><i class="is-void"></i>ยกเลิก</span>
        </div>
    </div>

    @if ($this->rows->isEmpty())
        <div class="rc-card"><p class="rc-empty">ไม่มีส่วนแบ่งในเดือนนี้ (เฉพาะงานของเทรนเนอร์ภายนอกที่ยืนยันแล้ว)</p></div>
    @elseif ($mode === 'trainer')
        @foreach ($this->byTrainer as $group)
            @php($t = $group['trainer'])
            @php($s = $group['summary'])
            <section class="rc-card te-group">
                <header class="rc-head rc-head--day">
                    <div class="rc-head__title">
                        <h2>{{ $t?->user?->name ?? '-' }} <span class="te-code">{{ $t?->code }}</span></h2>
                        <p>{{ $s['jobs'] }} งาน · {{ $s['hours'] }} ชม. · ส่วนแบ่ง <strong>฿{{ number_format($s['amount']) }}</strong> · ลูกค้าจ่าย ฿{{ number_format($s['customer']) }} · ยิมได้ ฿{{ number_format($s['facility']) }}</p>
                        @if ($t?->hasBankAccount() || $t?->promptpay_id)
                            <p class="te-bank">
                                @if ($t->bank())<span class="te-bank__chip" style="background: {{ $t->bank()->color() }}">{{ $t->bank()->value }}</span>@endif
                                <span>{{ $t->bankLabel() }}</span>
                                @if ($t->bank_account_number)<button type="button" class="te-copy" x-data x-on:click="navigator.clipboard.writeText('{{ $t->bank_account_number }}'); $tooltip('คัดลอกเลขบัญชีแล้ว', { timeout: 1500 })">คัดลอกเลขบัญชี</button>@endif
                                @if ($t->bank_updated_at && $t->bank_updated_at->gt(now()->subDays(14)))<span class="te-bank__new">เปลี่ยนเมื่อ {{ $t->bank_updated_at->format('d/m') }} ตรวจก่อนโอน</span>@endif
                            </p>
                        @else
                            <p class="te-bank te-bank--none">ยังไม่ได้กรอกบัญชีรับเงิน (เทรนเนอร์กรอกเองที่หน้า "รายได้")</p>
                        @endif
                    </div>
                    <div class="te-group__side">
                        <span class="te-pill is-paid">จ่ายแล้ว ฿{{ number_format($s['paid']) }}</span>
                        <span class="te-pill is-payable">รอจ่าย ฿{{ number_format($s['payable']) }}</span>
                        @if ($s['payable'] > 0 && $t)
                            {{ ($this->payAction)(['trainer' => $t->id]) }}
                        @endif
                        @if ($s['paid'] > 0 && $t)
                            {{ ($this->unpayAction)(['trainer' => $t->id]) }}
                        @endif
                    </div>
                </header>
                <table class="te-table">
                    <thead><tr><th>วันที่ใช้งาน</th><th>ผู้ชำระ / เลขอ้างอิง</th><th class="num">คน</th><th class="num">ชม.</th><th class="num">฿/ชม.</th><th class="num">ส่วนแบ่ง</th><th class="num">ลูกค้าจ่าย</th><th>สถานะ</th></tr></thead>
                <tbody>
                    @foreach ($group['rows'] as $e)
                        <tr class="{{ $e->counts() ? '' : 'is-muted' }}">
                            <td>{{ $e->earned_at->locale('th')->isoFormat('ddd D MMM') }} <span class="te-dim">{{ $e->reservation?->timeLabel() }}</span></td>
                            <td><a href="{{ $this->recordUrl($e) }}" class="te-link">{{ $e->reservation?->payer?->user?->name ?? '-' }}</a> <span class="te-dim">{{ $e->reservation?->reference }}</span></td>
                            <td class="num">{{ $e->people }}</td>
                            <td class="num">{{ $e->hours }}</td>
                            <td class="num">{{ number_format((float) $e->per_hour) }}</td>
                            <td class="num"><strong>{{ number_format((float) $e->amount) }}</strong></td>
                            <td class="num">{{ number_format((float) $e->customer_amount) }}</td>
                            <td><span class="rc-status {{ $statusClass($e) }}">{{ $e->status->label() }}</span>@if ($e->paid_at)<span class="te-dim"> {{ $e->paid_at->format('d/m') }}{{ $e->payout_note ? ' · '.$e->payout_note : '' }}</span>@endif@if ($e->void_reason)<span class="te-dim"> {{ $e->void_reason }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
                </table>
            </section>
        @endforeach
    @else
        <section class="rc-card">
            <table class="te-table">
                <thead><tr><th>วัน</th><th class="num">งาน</th><th class="num">ชม.</th><th class="num">ส่วนแบ่ง</th><th class="num">ยิมได้</th><th class="num">ลูกค้าจ่าย</th><th>เทรนเนอร์</th></tr></thead>
                <tbody>
                    @foreach ($this->byDay as $ymd => $items)
                        @php($s = \App\Services\TrainerEarningService::summarize($items))
                        <tr>
                            <td><strong>{{ \Carbon\CarbonImmutable::parse($ymd)->locale('th')->isoFormat('ddd D MMM') }}</strong></td>
                            <td class="num">{{ $s['jobs'] }}</td>
                            <td class="num">{{ $s['hours'] }}</td>
                            <td class="num"><strong>{{ number_format($s['amount']) }}</strong></td>
                            <td class="num">{{ number_format($s['facility']) }}</td>
                            <td class="num">{{ number_format($s['customer']) }}</td>
                            <td>{{ $items->map(fn ($e) => $e->trainer?->user?->nickname ?: $e->trainer?->user?->first_name)->unique()->implode(', ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php($s = \App\Services\TrainerEarningService::summarize($this->rows))
                    <tr><td><strong>รวมเดือนนี้</strong></td><td class="num">{{ $s['jobs'] }}</td><td class="num">{{ $s['hours'] }}</td><td class="num"><strong>{{ number_format($s['amount']) }}</strong></td><td class="num">{{ number_format($s['facility']) }}</td><td class="num">{{ number_format($s['customer']) }}</td><td></td></tr>
                </tfoot>
            </table>
        </section>
    @endif
</x-filament-panels::page>
