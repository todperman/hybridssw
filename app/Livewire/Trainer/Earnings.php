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
