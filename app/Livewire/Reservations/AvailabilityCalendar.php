<?php

namespace App\Livewire\Reservations;

use App\Models\Branch;
use App\Services\Reservations\CalendarService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * ปฏิทินความว่างของยิม ดูทั้งเดือนแล้วเจาะเป็นรายชั่วโมง กดจองจากช่องว่างได้เลย
 * ใช้ทั้ง Trainee และ Trainer ต่างกันแค่ปุ่มจองพาไปหน้าไหน
 */
class AvailabilityCalendar extends Component
{
    #[Locked]
    public string $mode = 'trainee';

    #[Url(as: 'm')]
    public string $month = '';

    #[Url(as: 'd')]
    public string $date = '';

    /** ช่องที่แตะไว้เพื่อเลือกจำนวนชั่วโมง */
    public ?string $picked = null;

    public function mount(): void
    {
        $this->mode = BookingWizard::modeFor(auth()->user());
        $today = CarbonImmutable::today();

        $this->date = $this->validDate($this->date) ?? $today->toDateString();
        $this->month = $this->validDate($this->month) ? CarbonImmutable::parse($this->month)->startOfMonth()->toDateString() : CarbonImmutable::parse($this->date)->startOfMonth()->toDateString();
    }

    #[Computed]
    public function branch(): Branch
    {
        $user = auth()->user();

        return $this->mode === 'trainee' ? $user->member->branch : $user->trainer->branch;
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month)->startOfMonth();
    }

    #[Computed]
    public function days(): array
    {
        return app(CalendarService::class)->month($this->branch, $this->monthStart);
    }

    #[Computed]
    public function slots(): array
    {
        return app(CalendarService::class)->day($this->branch, CarbonImmutable::parse($this->date), auth()->user());
    }

    /** เลื่อนเดือนได้ตั้งแต่เดือนนี้ถึงเดือนที่ยังอยู่ในระยะจองล่วงหน้า */
    #[Computed]
    public function canGoBack(): bool
    {
        return $this->monthStart->gt(CarbonImmutable::today()->startOfMonth());
    }

    #[Computed]
    public function canGoForward(): bool
    {
        return $this->monthStart->addMonth()->lte(CarbonImmutable::today()->addDays((int) $this->branch->booking_window_days));
    }

    public function shiftMonth(int $by): void
    {
        if (($by < 0 && ! $this->canGoBack) || ($by > 0 && ! $this->canGoForward)) {
            return;
        }

        $this->month = $this->monthStart->addMonths($by)->toDateString();
        $this->picked = null;
        unset($this->monthStart, $this->days, $this->canGoBack, $this->canGoForward);
    }

    public function selectDay(string $date): void
    {
        if (! $this->validDate($date)) {
            return;
        }

        $this->date = $date;
        $this->picked = null;

        $month = CarbonImmutable::parse($date)->startOfMonth()->toDateString();

        if ($month !== $this->month) {
            $this->month = $month;
            unset($this->monthStart, $this->days, $this->canGoBack, $this->canGoForward);
        }

        unset($this->slots);
    }

    public function pick(?string $time): void
    {
        $this->picked = $this->picked === $time ? null : $time;
    }

    /** ไปหน้าจองพร้อมวัน เวลา และจำนวนชั่วโมงที่เลือกไว้ */
    public function book(string $time, int $hours): void
    {
        $route = $this->mode === 'trainee' ? 'member.book' : 'trainer.book';

        $this->redirectRoute($route, ['date' => $this->date, 'start' => $time, 'hours' => max(1, $hours)], navigate: true);
    }

    protected function validDate(?string $value): ?string
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.reservations.availability-calendar')->layout('layouts.app');
    }
}
