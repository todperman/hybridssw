<?php

namespace App\Livewire\Member;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Member;
use App\Services\BookingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MyBookings extends Component
{
    public function member(): Member
    {
        $member = auth()->user()->member?->loadMissing('branch');

        // ผู้ใช้ที่ไม่มีโปรไฟล์ลูกทีม (เช่น เทรนเนอร์หรือเจ้าหน้าที่) ไม่ควรเข้าหน้านี้
        abort_if($member === null, 403, 'หน้านี้สำหรับลูกทีมเท่านั้น');

        return $member;
    }

    #[Computed]
    public function upcoming(): Collection
    {
        return Booking::where('member_id', $this->member()->id)
            ->whereIn('status', [BookingStatus::Booked->value, BookingStatus::Waitlisted->value, BookingStatus::CheckedIn->value])
            ->whereHas('workoutSession', fn ($q) => $q->where('ends_at', '>=', now()))
            ->with(['workoutSession.branch', 'trainer.user'])
            ->get()
            ->sortBy(fn (Booking $b) => $b->workoutSession->starts_at)
            ->values();
    }

    #[Computed]
    public function history(): Collection
    {
        return Booking::where('member_id', $this->member()->id)
            ->whereHas('workoutSession', fn ($q) => $q->where('ends_at', '<', now()))
            ->with(['workoutSession', 'trainer.user'])
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /** ยืนยันสิทธิ์หลังถูกเลื่อนขึ้นจากคิวสำรอง */
    public function confirm(int $bookingId, BookingService $bookings): void
    {
        $booking = $this->ownedBooking($bookingId);

        $bookings->confirmPromotion($booking);

        $this->dispatch('toast',
            tone: 'success',
            title: 'ยืนยันสิทธิ์แล้ว',
            body: 'ที่นั่งเป็นของคุณเรียบร้อย',
        );
        unset($this->upcoming);
    }

    public function cancel(int $bookingId, BookingService $bookings): void
    {
        $booking = $this->ownedBooking($bookingId);

        try {
            $bookings->cancel($booking, auth()->user(), 'ลูกทีมยกเลิกเอง');

            $this->dispatch('toast',
                tone: $booking->fresh()->cancelled_late ? 'warning' : 'success',
                title: 'ยกเลิกคิวแล้ว',
                body: $booking->fresh()->cancelled_late
                    ? 'เลยกำหนดยกเลิกฟรี จึงถูกหักเครดิต'
                    : 'คืนเครดิตให้เรียบร้อย',
            );
        } catch (BookingException $e) {
            $this->dispatch('toast', tone: 'error', title: 'ยกเลิกไม่ได้', body: $e->getMessage());
        }

        unset($this->upcoming);
    }

    protected function ownedBooking(int $bookingId): Booking
    {
        $booking = Booking::with('workoutSession')->findOrFail($bookingId);

        abort_unless($booking->member_id === $this->member()->id, 403);

        return $booking;
    }

    public function render()
    {
        return view('livewire.member.my-bookings')->layout('layouts.app');
    }
}
