<?php

namespace App\Livewire\Member;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Member;
use App\Models\WorkoutSession;
use App\Services\BookingService;
use App\Support\BookingRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * สมาชิกเลือกวันแล้วจองรอบให้ตัวเอง
 *
 * แสดงเฉพาะรอบแบบแบ่งที่นั่ง เพราะรอบเหมาสงวนไว้ให้เทรนเนอร์
 * ตัดสินจริงทุกอย่างที่ BookingService::bookSelf() หน้านี้แค่ซ่อนปุ่มที่กดไปก็ไม่ผ่าน
 */
class Schedule extends Component
{
    #[Url(as: 'd')]
    public string $day = '';

    public function mount(): void
    {
        $this->member();

        if (! $this->isSelectableDay($this->day)) {
            $this->day = now()->toDateString();
        }
    }

    public function member(): Member
    {
        $member = auth()->user()->member?->loadMissing('branch');

        abort_if($member === null, 403, 'หน้านี้สำหรับสมาชิกเท่านั้น');

        return $member;
    }

    /** วันที่เลือกได้ ตั้งแต่วันนี้ถึงขอบเขตจองล่วงหน้า */
    #[Computed]
    public function days(): Collection
    {
        $today = CarbonImmutable::today();

        return collect(range(0, BookingRules::selfAdvanceDays()))
            ->map(fn (int $i) => $today->addDays($i));
    }

    public function selectDay(string $date): void
    {
        if ($this->isSelectableDay($date)) {
            $this->day = $date;
        }
    }

    #[Computed]
    public function sessions(): Collection
    {
        return WorkoutSession::query()
            ->where('branch_id', $this->member()->branch_id)
            ->whereDate('date', $this->day)
            ->where('mode', SessionMode::Shared->value)
            ->where('status', SessionStatus::Open->value)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get();
    }

    /** การจองของเราในรอบที่เห็นอยู่ เรียงตาม session id ไว้เช็คว่ารอบไหนจองไปแล้ว */
    #[Computed]
    public function mine(): Collection
    {
        return Booking::query()
            ->where('member_id', $this->member()->id)
            ->whereIn('workout_session_id', $this->sessions->pluck('id'))
            ->whereIn('status', BookingStatus::seatHolding())
            ->get()
            ->keyBy('workout_session_id');
    }

    /** จำนวนรอบที่ยังจองได้ในแต่ละวัน แสดงเป็นจุดใต้วันที่ */
    #[Computed]
    public function openCountByDay(): Collection
    {
        $days = $this->days;

        return WorkoutSession::query()
            ->where('branch_id', $this->member()->branch_id)
            ->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->where('mode', SessionMode::Shared->value)
            ->where('status', SessionStatus::Open->value)
            ->where('starts_at', '>', now())
            ->whereColumn('booked_count', '<', 'capacity')
            ->selectRaw('DATE(date) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd');
    }

    public function book(int $sessionId, BookingService $bookings): void
    {
        $member = $this->member();
        $session = WorkoutSession::where('branch_id', $member->branch_id)->findOrFail($sessionId);

        try {
            $booking = $bookings->bookSelf($session, $member, auth()->user());
        } catch (BookingException $e) {
            $this->dispatch('toast', tone: 'error', title: 'จองไม่สำเร็จ', body: $e->getMessage());

            return;
        }

        unset($this->sessions, $this->mine, $this->openCountByDay);

        $pending = $booking->status === BookingStatus::Pending;

        $this->dispatch('toast',
            tone: 'success',
            title: $pending ? 'ส่งคำขอจองแล้ว' : 'จองสำเร็จ',
            body: $pending
                ? 'ที่นั่งถูกกันไว้ให้แล้ว รอแอดมินยืนยัน ดูสถานะได้ที่คิวของฉัน'
                : 'รอบ '.$session->starts_at->format('d/m H:i').' เป็นของคุณแล้ว',
        );
    }

    protected function isSelectableDay(string $date): bool
    {
        // Carbon แปลงข้อความว่างเป็น "ตอนนี้" ต้องดักไว้ก่อน ไม่งั้นค่าว่างจะผ่านเป็นวันนี้
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            $day = CarbonImmutable::parse($date)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $day->betweenIncluded(
            CarbonImmutable::today(),
            CarbonImmutable::today()->addDays(BookingRules::selfAdvanceDays()),
        );
    }

    public function render()
    {
        return view('livewire.member.schedule')->layout('layouts.app');
    }
}
