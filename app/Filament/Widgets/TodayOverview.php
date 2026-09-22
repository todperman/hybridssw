<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Enums\TrainerStatus;
use App\Models\Booking;
use App\Models\Trainer;
use App\Models\WorkoutSession;
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
        $today = now()->toDateString();

        $sessions = $this->scoped(WorkoutSession::query())
            ->whereDate('date', $today)
            ->where('status', '!=', SessionStatus::Cancelled->value)
            ->get();

        $capacity = $sessions->sum('capacity');
        $booked = $sessions->sum('booked_count');

        // อัตราการใช้ที่นั่ง คือ ตัวเลขที่บอกว่าควรเพิ่มรอบหรือควรลดรอบ
        $utilisation = $capacity > 0 ? round($booked / $capacity * 100) : 0;

        $checkedIn = Booking::whereIn('workout_session_id', $sessions->pluck('id'))
            ->whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::Completed->value])
            ->count();

        $waitlisted = $sessions->sum('waitlist_count');

        $pendingTrainers = $this->scoped(Trainer::query())
            ->where('status', TrainerStatus::Pending->value)
            ->count();

        $expiringCerts = $this->scoped(Trainer::query())
            ->where('status', TrainerStatus::Approved->value)
            ->whereNotNull('certification_expires_at')
            ->whereDate('certification_expires_at', '<=', now()->addDays(30))
            ->count();

        return [
            Stat::make('รอบวันนี้', $sessions->count())
                ->description("{$booked} / {$capacity} ที่นั่งถูกจอง")
                ->color('primary'),

            Stat::make('อัตราการใช้ที่นั่ง', "{$utilisation}%")
                ->description($utilisation >= 80 ? 'เกือบเต็ม ควรพิจารณาเปิดรอบเพิ่ม' : 'ยังมีที่ว่าง')
                ->color($utilisation >= 80 ? 'warning' : 'success'),

            Stat::make('เช็คอินแล้ว', $checkedIn)
                ->description($waitlisted > 0 ? "มีคิวสำรองรออยู่ {$waitlisted} คน" : 'ไม่มีคิวสำรอง')
                ->color('success'),

            Stat::make('เทรนเนอร์รออนุมัติ', $pendingTrainers)
                ->description($expiringCerts > 0 ? "ใบรับรองใกล้หมดอายุ {$expiringCerts} คน" : 'เอกสารครบทุกคน')
                ->color($pendingTrainers > 0 || $expiringCerts > 0 ? 'warning' : 'gray'),
        ];
    }

    /** ผู้ที่ไม่ใช่แอดมินเห็นตัวเลขเฉพาะสาขาตัวเอง ให้ตรงกับที่เห็นในตาราง */
    protected function scoped(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->where('branch_id', $user->branch_id ?? 0);
    }
}
