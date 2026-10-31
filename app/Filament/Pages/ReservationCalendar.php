<?php

namespace App\Filament\Pages;

use App\Enums\ReservationStatus;
use App\Enums\TrainerStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Branch;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Services\Reservations\CalendarService;
use App\Services\Reservations\OpeningHours;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * ปฏิทินการจองของสาขา ดูทั้งเดือนว่าวันไหนมีกี่รายการ แล้วเจาะรายชั่วโมงของวันเดียว
 * คลิกรายการไปหน้าการจองนั้นได้เลย ใช้ขอบเขตสาขาเดียวกับตารางการจอง
 */
class ReservationCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'ปฏิทินการจอง';

    protected static ?string $title = 'ปฏิทินการจอง';

    protected static ?string $slug = 'reservations-calendar';

    protected string $view = 'filament.pages.reservation-calendar';

    #[Url(as: 'm')]
    public string $month = '';

    #[Url(as: 'd')]
    public string $date = '';

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    #[Url(as: 'trainer')]
    public ?int $trainerId = null;

    /** แสดงรายการที่ยกเลิก/หมดอายุด้วย (ปกติซ่อน) */
    #[Url(as: 'closed')]
    public bool $showClosed = false;

    public function mount(): void
    {
        $today = CarbonImmutable::today();
        $this->date = $this->validDate($this->date) ?? $today->toDateString();
        $this->month = $this->validDate($this->month) ? CarbonImmutable::parse($this->month)->startOfMonth()->toDateString() : CarbonImmutable::parse($this->date)->startOfMonth()->toDateString();

        $allowed = $this->branches()->pluck('id');

        if (! $this->branchId || ! $allowed->contains($this->branchId)) {
            $this->branchId = auth()->user()?->branch_id && $allowed->contains(auth()->user()->branch_id)
                ? auth()->user()->branch_id
                : $allowed->first();
        }
    }

    /** สาขาที่ผู้ใช้คนนี้ดูได้ แอดมินทุกสาขา เจ้าหน้าที่เฉพาะสาขาตัวเอง */
    public function branches(): Collection
    {
        $user = auth()->user();
        $query = Branch::query()->where('is_active', true)->orderBy('name');

        if ($user && ! $user->canSeeAllBranches()) {
            $query->where('id', $user->branch_id ?? 0);
        }

        return $query->get(['id', 'name']);
    }

    #[Computed]
    public function branch(): ?Branch
    {
        return $this->branchId ? Branch::find($this->branchId) : null;
    }

    #[Computed]
    public function trainers(): Collection
    {
        return Trainer::query()
            ->where('branch_id', $this->branchId)
            ->where('status', TrainerStatus::Approved->value)
            ->with('user')
            ->get()
            ->sortBy(fn (Trainer $t) => $t->user?->name)
            ->values();
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month)->startOfMonth();
    }

    /** การจองทั้งเดือนของสาขา (ตามตัวกรอง) จัดกลุ่มตามวัน ถามครั้งเดียว */
    #[Computed]
    public function byDay(): Collection
    {
        $from = $this->monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $to = $this->monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        return $this->query()
            ->where('starts_at', '<', $to->addDay())
            ->where('ends_at', '>', $from)
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Reservation $r) => $r->starts_at->toDateString());
    }

    /** @return array<int, array{start: CarbonImmutable, reservation: ?Reservation, first: bool}> */
    #[Computed]
    public function daySlots(): array
    {
        $branch = $this->branch;

        if (! $branch) {
            return [];
        }

        $date = CarbonImmutable::parse($this->date);
        $slots = app(OpeningHours::class)->slotsOn($branch, $date);
        $reservations = $this->byDay->get($date->toDateString(), collect());
        $rows = [];

        foreach ($slots as $slot) {
            $hit = $reservations->first(fn (Reservation $r) => $r->status->holdsSlots() && $r->starts_at->lte($slot) && $r->ends_at->gt($slot));
            $rows[] = ['start' => $slot, 'reservation' => $hit, 'first' => $hit && $hit->starts_at->equalTo($slot)];
        }

        return $rows;
    }

    /** รายการของวันที่เลือกทั้งหมด (รวมที่ยกเลิก/หมดอายุถ้าเปิดดู) ไว้แสดงเป็นรายการใต้ตาราง */
    #[Computed]
    public function dayList(): Collection
    {
        return $this->byDay->get($this->date, collect());
    }

    public function monthSummary(): array
    {
        $all = $this->byDay->flatten(1)->filter(fn (Reservation $r) => $r->starts_at->month === $this->monthStart->month);

        return [
            'count' => $all->where('status', ReservationStatus::Confirmed)->count(),
            'pending' => $all->where('status', ReservationStatus::PendingPayment)->count(),
            'hours' => (int) $all->filter(fn (Reservation $r) => $r->status->holdsSlots())->sum('hours'),
            'amount' => (float) $all->where('status', ReservationStatus::Confirmed)->sum('amount'),
        ];
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart->addMonths($by)->toDateString();
        $this->forgetComputed();
    }

    public function goToday(): void
    {
        $this->date = CarbonImmutable::today()->toDateString();
        $this->month = CarbonImmutable::today()->startOfMonth()->toDateString();
        $this->forgetComputed();
    }

    public function selectDay(string $date): void
    {
        if (! $this->validDate($date)) {
            return;
        }

        $this->date = $date;
        $month = CarbonImmutable::parse($date)->startOfMonth()->toDateString();

        if ($month !== $this->month) {
            $this->month = $month;
        }

        $this->forgetComputed();
    }

    public function updated(string $property): void
    {
        if ($property === 'branchId') {
            $this->trainerId = null;
        }

        $this->forgetComputed();
    }

    public function recordUrl(Reservation $reservation): string
    {
        return ReservationResource::getUrl('view', ['record' => $reservation]);
    }

    public static function gridDays(CarbonImmutable $month): Collection
    {
        return CalendarService::gridDays($month);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('today')->label('วันนี้')->icon('heroicon-o-calendar')->color('gray')->action(fn () => $this->goToday()),
            Action::make('list')->label('ดูเป็นรายการ')->icon('heroicon-o-table-cells')->color('gray')->url(ReservationResource::getUrl('index')),
        ];
    }

    protected function query()
    {
        $statuses = $this->showClosed
            ? array_map(fn (ReservationStatus $s) => $s->value, ReservationStatus::cases())
            : ReservationStatus::holding();

        return ReservationResource::getEloquentQuery()
            ->where('branch_id', $this->branchId ?? 0)
            ->whereIn('status', $statuses)
            ->when($this->trainerId, fn ($q) => $q->where('trainer_id', $this->trainerId))
            ->with(['payer.user', 'trainer.user'])
            ->withCount('participants');
    }

    protected function forgetComputed(): void
    {
        unset($this->branch, $this->trainers, $this->monthStart, $this->byDay, $this->daySlots, $this->dayList);
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
}
