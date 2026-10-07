<?php

namespace App\Livewire;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * แจ้งเตือนก่อนถึงเวลาที่จองยิมไว้
 *
 * วางไว้ในเลย์เอาต์หลัก จึงติดตามผู้ใช้ไปทุกหน้า
 * ฝั่งเซิร์ฟเวอร์ถามข้อมูลทุก 60 วินาที ส่วนตัวนับถอยหลังเดินฝั่งเบราว์เซอร์
 * จะได้ไม่ต้องยิงคำขอทุกวินาทีเพียงเพื่ออัปเดตตัวเลข
 */
class UpcomingReminder extends Component
{
    /** การจองที่ผู้ใช้กดปิดไปแล้ว เก็บไว้ไม่ให้เด้งซ้ำภายในหน้าเดียวกัน */
    public array $dismissed = [];

    public function dismiss(int $reservationId): void
    {
        $this->dismissed[] = $reservationId;

        unset($this->reservation);
    }

    /**
     * การจองที่ยืนยันแล้วและใกล้ถึงเวลาที่สุด
     * เตือนทั้งผู้เข้าร่วม ผู้ชำระ และ Trainer ประจำงาน
     */
    #[Computed]
    public function reservation(): ?Reservation
    {
        $user = auth()->user();

        if (! $user || (! $user->member && ! $user->trainer)) {
            return null;
        }

        $lead = (int) config('gym.reminder.lead_minutes', 90);
        $grace = (int) config('gym.reminder.grace_minutes', 15);

        return Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereBetween('starts_at', [now()->subMinutes($grace), now()->addMinutes($lead)])
            ->when($this->dismissed !== [], fn ($q) => $q->whereNotIn('id', $this->dismissed))
            ->where(function ($q) use ($user) {
                if ($user->member) {
                    $q->orWhere('payer_member_id', $user->member->id)
                        ->orWhereHas('participants', fn ($p) => $p->whereKey($user->member->id));
                }

                if ($user->trainer) {
                    $q->orWhere('trainer_id', $user->trainer->id);
                }
            })
            ->with(['branch', 'trainer.user'])
            ->withCount('participants')
            ->orderBy('starts_at')
            ->first();
    }

    public function render()
    {
        return view('livewire.upcoming-reminder');
    }
}
