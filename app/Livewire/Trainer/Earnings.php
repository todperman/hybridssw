<?php

namespace App\Livewire\Trainer;

use App\Enums\EarningStatus;
use App\Models\Trainer;
use App\Models\TrainerEarning;
use App\Services\TrainerEarningService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * รายได้ของเทรนเนอร์ (ส่วนแบ่งต่อการจอง) ดูรายเดือน รายวัน และสถานะจ่าย
 * เห็นเฉพาะของตัวเอง เทรนเนอร์ภายในเห็นหน้านี้ได้แต่จะว่างเพราะไม่มีส่วนแบ่ง
 */
class Earnings extends Component
{
    #[Url(as: 'm')]
    public string $month = '';

    /* ---------- บัญชีรับเงิน ---------- */

    public bool $editingBank = false;

    public string $bank_code = '';

    public string $bank_account_number = '';

    public string $bank_account_name = '';

    public string $promptpay_id = '';

    public function openBank(): void
    {
        $t = $this->trainer();
        $this->bank_code = (string) $t->bank_code;
        $this->bank_account_number = (string) $t->bank_account_number;
        $this->bank_account_name = (string) ($t->bank_account_name ?: $t->user->name);
        $this->promptpay_id = (string) $t->promptpay_id;
        $this->resetValidation();
        $this->editingBank = true;
    }

    public function saveBank(): void
    {
        $digits = preg_replace('/\D+/', '', $this->bank_account_number);
        $bank = \App\Enums\ThaiBank::tryFrom($this->bank_code);
        [$min, $max] = $bank?->accountDigits() ?? [10, 15];

        $data = $this->validate([
            'bank_code' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Enums\ThaiBank::options()))],
            'bank_account_number' => ['required', 'regex:/^[0-9\- ]+$/', function ($attr, $value, $fail) use ($digits, $min, $max) {
                if (strlen($digits) < $min || strlen($digits) > $max) {
                    $fail($min === $max ? "เลขบัญชีต้องมี {$min} หลัก" : "เลขบัญชีต้องมี {$min}–{$max} หลัก");
                }
            }],
            'bank_account_name' => ['required', 'string', 'max:120'],
            'promptpay_id' => ['nullable', 'regex:/^[0-9\- ]+$/', function ($attr, $value, $fail) {
                $d = preg_replace('/\D+/', '', (string) $value);
                if ($d !== '' && ! in_array(strlen($d), [10, 13], true)) {
                    $fail('พร้อมเพย์ต้องเป็นเบอร์โทร 10 หลัก หรือเลขบัตรประชาชน 13 หลัก');
                }
            }],
        ], [], [
            'bank_code' => 'ธนาคาร', 'bank_account_number' => 'เลขบัญชี', 'bank_account_name' => 'ชื่อบัญชี', 'promptpay_id' => 'พร้อมเพย์',
        ]);

        $this->trainer()->updateBankAccount($data, auth()->user());

        $this->editingBank = false;
        $this->dispatch('toast', tone: 'success', title: 'บันทึกบัญชีรับเงินแล้ว', body: 'แอดมินจะใช้บัญชีนี้โอนส่วนแบ่งรอบถัดไป');
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->trainer, 403);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        app(TrainerEarningService::class)->settle();
    }

    public function trainer(): Trainer
    {
        return auth()->user()->trainer;
    }

    public function eligible(): bool
    {
        return app(TrainerEarningService::class)->eligible($this->trainer());
    }

    /** ตารางส่วนแบ่งของสาขา ไว้ให้เทรนเนอร์เห็นว่ากี่คนได้เท่าไร (ปิดได้จากหลังบ้าน) */
    public function shareTable(): array
    {
        if (! \App\Support\PayoutSettings::load()['visible']) {
            return [];
        }

        $branch = $this->trainer()->branch;
        $payout = $branch->payoutTable();
        $prices = $branch->priceTable();
        $max = max(1, (int) $branch->max_trainees);

        return collect(range(1, $max))->map(fn (int $n) => [
            'people' => $n,
            'trainer' => $payout->trainerPerHour($n),
            'price' => $prices->perHour($n, true),
        ])->all();
    }

    public function policyLines(): array
    {
        return \App\Support\PayoutSettings::policyLines();
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month.'-01')->startOfMonth();
    }

    /** @return Collection<int, TrainerEarning> */
    #[Computed]
    public function rows(): Collection
    {
        return TrainerEarning::query()
            ->where('trainer_id', $this->trainer()->id)
            ->counting()
            ->whereBetween('earned_at', [$this->monthStart, $this->monthStart->endOfMonth()])
            ->with('reservation.payer.user')
            ->withCount([])
            ->orderBy('earned_at')
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        return TrainerEarningService::summarize($this->rows);
    }

    /** ยอดต่อเดือน 6 เดือนล่าสุด (ไว้วาดกราฟแท่ง) */
    #[Computed]
    public function history(): array
    {
        $from = $this->monthStart->subMonths(5);
        $rows = TrainerEarning::query()
            ->where('trainer_id', $this->trainer()->id)
            ->counting()
            ->whereBetween('earned_at', [$from, $this->monthStart->endOfMonth()])
            ->get(['earned_at', 'amount', 'status']);

        $out = [];

        for ($m = $from; $m->lte($this->monthStart); $m = $m->addMonth()) {
            $inMonth = $rows->filter(fn (TrainerEarning $e) => $e->earned_at->isSameMonth($m));
            $out[] = ['month' => $m, 'amount' => (float) $inMonth->sum('amount'), 'paid' => (float) $inMonth->where('status', EarningStatus::Paid)->sum('amount')];
        }

        return $out;
    }

    #[Computed]
    public function outstanding(): float
    {
        return (float) TrainerEarning::where('trainer_id', $this->trainer()->id)->where('status', EarningStatus::Payable->value)->sum('amount');
    }

    #[Computed]
    public function lastPaid(): ?TrainerEarning
    {
        return TrainerEarning::where('trainer_id', $this->trainer()->id)->where('status', EarningStatus::Paid->value)->latest('paid_at')->first();
    }

    public function shiftMonth(int $by): void
    {
        $next = $this->monthStart->addMonths($by);

        if ($next->gt(now()->startOfMonth()->addMonth())) {
            return;
        }

        $this->month = $next->format('Y-m');
        unset($this->monthStart, $this->rows, $this->summary, $this->history);
    }

    public function render()
    {
        return view('livewire.trainer.earnings')->layout('layouts.app');
    }
}
