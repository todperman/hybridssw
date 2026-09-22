<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\WorkoutSession;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * แจ้งเตือนก่อนถึงรอบที่จองไว้
 *
 * วางไว้ในเลย์เอาต์หลัก จึงติดตามผู้ใช้ไปทุกหน้า
 * ฝั่งเซิร์ฟเวอร์ถามข้อมูลทุก 60 วินาที ส่วนตัวนับถอยหลังเดินฝั่งเบราว์เซอร์
 * จะได้ไม่ต้องยิงคำขอทุกวินาทีเพียงเพื่ออัปเดตตัวเลข
 */
class UpcomingReminder extends Component
{
    /** รอบที่ผู้ใช้กดปิดไปแล้ว เก็บไว้ไม่ให้เด้งซ้ำภายในหน้าเดียวกัน */
    public array $dismissed = [];

    public function dismiss(int $bookingId): void
    {
        $this->dismissed[] = $bookingId;

        unset($this->booking);
    }

    /**
     * หารอบถัดไปที่ควรเตือน
     *
     * เตือนทั้งฝั่งลูกทีม (รอบของตัวเอง) และฝั่งเทรนเนอร์ (รอบที่จองให้ลูกทีม)
     * ถ้าเป็นทั้งสองอย่าง ให้เอารอบที่ใกล้ที่สุดก่อน
     */
    #[Computed]
    public function booking(): ?Booking
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $lead = (int) config('gym.reminder.lead_minutes', 90);
        $grace = (int) config('gym.reminder.grace_minutes', 15);

        $from = now()->subMinutes($grace);
        $until = now()->addMinutes($lead);

        return Booking::query()
            ->active()
            ->when($this->dismissed !== [], fn ($q) => $q->whereNotIn('id', $this->dismissed))
            ->where(function ($q) use ($user) {
                if ($user->member) {
                    $q->orWhere('member_id', $user->member->id);
                }

                if ($user->trainer) {
                    $q->orWhere('trainer_id', $user->trainer->id);
                }
            })
            ->whereHas('workoutSession', fn ($q) => $q
                ->whereBetween('starts_at', [$from, $until]))
            ->with(['workoutSession.branch', 'member.user'])
            // เรียงด้วย subquery แทนการ join เพราะทั้งสองตารางมีคอลัมน์ status
            // การ join ทำให้เงื่อนไข where ของ scopeActive กำกวมทันที
            ->orderBy(
                WorkoutSession::select('starts_at')
                    ->whereColumn('workout_sessions.id', 'bookings.workout_session_id')
            )
            ->first();
    }

    /** จำนวนลูกทีมของเราในรอบเดียวกัน ใช้บอกเทรนเนอร์ว่าต้องดูแลกี่คน */
    #[Computed]
    public function teamCount(): int
    {
        $booking = $this->booking;
        $trainer = auth()->user()?->trainer;

        if (! $booking || ! $trainer) {
            return 0;
        }

        return Booking::query()
            ->active()
            ->where('trainer_id', $trainer->id)
            ->where('workout_session_id', $booking->workout_session_id)
            ->count();
    }

    public function render()
    {
        return view('livewire.upcoming-reminder');
    }
}
