<?php

namespace App\Filament\Pages;

use App\Enums\EarningStatus;
use App\Enums\TrainerType;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Branch;
use App\Models\Trainer;
use App\Models\TrainerEarning;
use App\Services\TrainerEarningService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ส่วนแบ่งเทรนเนอร์: สรุปวันนี้/เดือนนี้/ค้างจ่าย ตารางรายเทรนเนอร์ (รายวันหรือรายเดือน) รายการต่อการจอง
 * จ่ายรอบรายเดือนต่อเทรนเนอร์ และส่งออก CSV ให้บัญชี ใช้ขอบเขตสาขาเดียวกับตารางการจอง
 */
class TrainerEarnings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'การจอง';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'ส่วนแบ่งเทรนเนอร์';

    protected static ?string $title = 'ส่วนแบ่งเทรนเนอร์';

    protected static ?string $slug = 'trainer-earnings';

    protected string $view = 'filament.pages.trainer-earnings';

    #[Url(as: 'm')]
    public string $month = '';

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    #[Url(as: 'trainer')]
    public ?int $trainerId = null;

    /** day = รายวันของเทรนเนอร์ที่เลือก, trainer = รายเทรนเนอร์ของเดือน */
    #[Url(as: 'view')]
    public string $mode = 'trainer';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role?->canAccessAdminPanel();
    }

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        $allowed = $this->branches()->pluck('id');

        if (! $this->branchId || ! $allowed->contains($this->branchId)) {
            $this->branchId = auth()->user()?->branch_id && $allowed->contains(auth()->user()->branch_id) ? auth()->user()->branch_id : $allowed->first();
        }

        if (! in_array($this->mode, ['trainer', 'day'], true)) {
            $this->mode = 'trainer';
        }

        app(TrainerEarningService::class)->settle();
    }

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
    public function trainers(): Collection
    {
        return Trainer::query()->where('branch_id', $this->branchId)->where('type', TrainerType::External->value)->with('user')->get()
            ->sortBy(fn (Trainer $t) => $t->user?->name)->values();
    }

    #[Computed]
    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month.'-01')->startOfMonth();
    }

    /** แถวทั้งหมดของเดือน (ตามสาขา/เทรนเนอร์) */
    #[Computed]
    public function rows(): Collection
    {
        return TrainerEarning::query()
            ->where('branch_id', $this->branchId ?? 0)
            ->when($this->trainerId, fn ($q) => $q->where('trainer_id', $this->trainerId))
            ->whereBetween('earned_at', [$this->monthStart, $this->monthStart->endOfMonth()])
            ->with(['trainer.user', 'reservation.payer.user'])
            ->orderBy('earned_at')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        $branch = $this->branchId ?? 0;
        $today = TrainerEarning::where('branch_id', $branch)->counting()->whereDate('earned_at', today())->get();
        $thisMonth = TrainerEarning::where('branch_id', $branch)->counting()->whereBetween('earned_at', [now()->startOfMonth(), now()->endOfMonth()])->get();
        $payable = TrainerEarning::where('branch_id', $branch)->where('status', EarningStatus::Payable->value)->get();

        return [
            'today' => TrainerEarningService::summarize($today),
            'month' => TrainerEarningService::summarize($thisMonth),
            'payable' => TrainerEarningService::summarize($payable),
        ];
    }

    /** @return Collection<int, array{trainer: Trainer, summary: array}> */
    #[Computed]
    public function byTrainer(): Collection
    {
        return $this->rows->groupBy('trainer_id')->map(fn (Collection $rows) => [
            'trainer' => $rows->first()->trainer,
            'summary' => TrainerEarningService::summarize($rows),
            'rows' => $rows,
        ])->sortBy(fn ($g) => $g['trainer']?->user?->name)->values();
    }

    /** @return Collection<string, Collection> วัน => แถว */
    #[Computed]
    public function byDay(): Collection
    {
        return $this->rows->filter(fn (TrainerEarning $e) => $e->counts())->groupBy(fn (TrainerEarning $e) => $e->earned_at->toDateString());
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart->addMonths($by)->format('Y-m');
        $this->forget();
    }

    public function updated(string $property): void
    {
        if ($property === 'branchId') {
            $this->trainerId = null;
        }

        $this->forget();
    }

    public function recordUrl(TrainerEarning $e): string
    {
        return ReservationResource::getUrl('view', ['record' => $e->reservation_id]);
    }

    /** ปุ่ม "จ่ายรอบนี้" ต่อเทรนเนอร์: จ่ายทุกรายการรอจ่ายที่หมดเวลาภายในเดือนที่ดูอยู่ */
    public function payAction(): Action
    {
        return Action::make('pay')
            ->label('จ่ายรอบนี้')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'จ่ายส่วนแบ่ง '.(Trainer::find($arguments['trainer'])?->user?->name ?? ''))
            ->modalDescription(fn (array $arguments) => 'รายการ "รอจ่าย" ทั้งหมดของ '.$this->monthStart->locale('th')->isoFormat('MMMM YYYY').' จะถูกบันทึกว่าจ่ายแล้ว และแจ้งเทรนเนอร์ทางกระดิ่งและอีเมล')
            ->schema([
                TextInput::make('note')->label('บันทึก (เช่น เลขอ้างอิงโอน วันที่โอน)')->maxLength(200),
            ])
            ->action(function (array $arguments, array $data) {
                $trainer = Trainer::findOrFail($arguments['trainer']);
                $result = app(TrainerEarningService::class)->pay($trainer, $this->monthStart, $this->monthStart->endOfMonth(), auth()->user(), $data['note'] ?? null);
                $this->forget();

                Notification::make()
                    ->title($result['count'] ? 'จ่ายแล้ว '.$result['count'].' งาน ฿'.number_format($result['amount']) : 'ไม่มีรายการรอจ่ายในเดือนนี้')
                    ->{$result['count'] ? 'success' : 'warning'}()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('ส่งออก CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->export()),
            Action::make('calendar')->label('ปฏิทินการจอง')->icon('heroicon-o-calendar')->color('gray')->url(ReservationCalendar::getUrl()),
        ];
    }

    public function export(): StreamedResponse
    {
        $rows = $this->rows;
        $name = 'trainer-earnings-'.$this->month.'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['วันที่ใช้งาน', 'เวลา', 'เทรนเนอร์', 'เลขอ้างอิง', 'ผู้ชำระ', 'จำนวนคน', 'ชั่วโมง', 'ค่า Trainer/ชม.', 'ส่วนแบ่ง', 'ค่า Facility', 'ลูกค้าจ่าย', 'สถานะ', 'จ่ายเมื่อ', 'บันทึก']);

            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->earned_at->format('Y-m-d'), $e->reservation?->timeLabel(), $e->trainer?->user?->name, $e->reservation?->reference,
                    $e->reservation?->payer?->user?->name, $e->people, $e->hours, $e->per_hour, $e->amount, $e->facility_amount,
                    $e->customer_amount, $e->status->label(), $e->paid_at?->format('Y-m-d H:i'), $e->payout_note,
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function forget(): void
    {
        unset($this->trainers, $this->monthStart, $this->rows, $this->stats, $this->byTrainer, $this->byDay);
    }
}
