<?php

namespace App\Filament\Widgets;

use App\Enums\RefundStatus;
use App\Enums\RequestStatus;
use App\Enums\ReservationStatus;
use App\Enums\TrainerStatus;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Models\Trainer;
use App\Services\Reservations\OpeningHours;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TodayOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'ภาพรวมวันนี้';

    // เรนเดอร์พร้อมหน้าเลย ไม่ต้องรอ lazy load รอบสอง ตัวเลขชุดนี้เบาพอ
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $today = now();

        $confirmed = $this->scoped(Reservation::query())
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereDate('starts_at', $today->toDateString())
            ->get(['id', 'hours', 'amount']);

        // ชั่วโมงที่ยิมเปิดวันนี้รวมทุกสาขาที่เห็น ใช้หาว่ายิมถูกจองไปกี่เปอร์เซ็นต์
        $openHours = $this->scoped(Branch::query())->where('is_active', true)->get()
            ->sum(fn (Branch $b) => count(app(OpeningHours::class)->slotsOn($b, $today)));
        $bookedHours = (int) $confirmed->sum('hours');
        $usage = $openHours > 0 ? round($bookedHours / $openHours * 100) : 0;

        $awaitingPayment = $this->scoped(Reservation::query())
            ->where('status', ReservationStatus::PendingPayment->value)
            ->where('hold_expires_at', '>', now())
            ->count();

        $requests = ReservationRequest::where('status', RequestStatus::Pending->value)
            ->whereHas('reservation', fn ($q) => $this->scoped($q))
            ->count();
        $review = Payment::where('needs_review', true)
            ->whereHas('reservation', fn ($q) => $this->scoped($q))
            ->count();
        $refunds = $this->scoped(Reservation::query())
            ->whereIn('refund_status', [RefundStatus::Pending->value, RefundStatus::Failed->value])
            ->count();
        $todo = $requests + $review + $refunds;

        $pendingTrainers = $this->scoped(Trainer::query())
            ->where('status', TrainerStatus::Pending->value)
            ->count();

        return [
            Stat::make('การจองวันนี้', $confirmed->count())
                ->description("{$bookedHours} จาก {$openHours} ชั่วโมงที่เปิด ({$usage}%)")
                ->color('primary'),

            Stat::make('รอชำระเงิน', $awaitingPayment)
                ->description($awaitingPayment > 0 ? 'ถ้าโอนมาแล้ว กดบันทึกรับชำระที่หน้าการจอง' : 'ไม่มีรายการค้าง')
                ->color($awaitingPayment > 0 ? 'warning' : 'gray'),

            Stat::make('รอแอดมินจัดการ', $todo)
                ->description($todo > 0
                    ? collect(['คำขอ '.$requests => $requests, 'ตรวจการชำระ '.$review => $review, 'คืนเงิน '.$refunds => $refunds])->filter()->keys()->join(' · ')
                    : 'ไม่มีงานค้าง')
                ->color($todo > 0 ? 'danger' : 'success'),

            Stat::make('Trainer รออนุมัติ', $pendingTrainers)
                ->color($pendingTrainers > 0 ? 'warning' : 'gray'),
        ];
    }

    /** ผู้ที่ไม่ใช่แอดมินเห็นตัวเลขเฉพาะสาขาตัวเอง ให้ตรงกับที่เห็นในตาราง */
    protected function scoped(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        $column = $query->getModel() instanceof Branch ? 'id' : 'branch_id';

        return $query->where($query->getModel()->getTable().'.'.$column, $user->branch_id ?? 0);
    }
}
