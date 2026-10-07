<?php

namespace App\Livewire\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * รายการจอง ใช้ทั้ง "การจองของฉัน" ของ Trainee และ "งาน" ของ Trainer
 *
 * Trainee เห็นการจองที่ตัวเองเป็นผู้เข้าร่วมหรือผู้ชำระ
 * Trainer เห็นงานที่ตัวเองเป็น Trainer ประจำ และรายการที่ตัวเองจองให้ลูกเทรน
 */
class ReservationList extends Component
{
    #[Locked]
    public string $mode = 'trainee';

    #[Url(as: 'tab')]
    public string $tab = 'upcoming';

    public function mount(): void
    {
        $this->mode = self::modeFor(auth()->user());
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['upcoming', 'past'], true) ? $tab : 'upcoming';
        unset($this->reservations);
    }

    protected function base(): Builder
    {
        $user = auth()->user();

        if ($this->mode === 'trainer') {
            $trainerId = $user->trainer->id;

            return Reservation::query()->where(fn ($q) => $q
                ->where('trainer_id', $trainerId)
                ->orWhere('created_by_user_id', $user->id));
        }

        $memberId = $user->member->id;

        return Reservation::query()->where(fn ($q) => $q
            ->where('payer_member_id', $memberId)
            ->orWhereHas('participants', fn ($p) => $p->whereKey($memberId)));
    }

    /** @return Collection<int, Reservation> */
    #[Computed]
    public function reservations(): Collection
    {
        $query = $this->base()->with(['trainer.user', 'payer.user', 'participants.user']);

        if ($this->tab === 'upcoming') {
            return $query->holding()->where('ends_at', '>', now())->orderBy('starts_at')->get();
        }

        return $query->where(fn ($q) => $q
            ->whereNotIn('status', ReservationStatus::holding())
            ->orWhere('ends_at', '<=', now()))
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function awaitingPayment(): int
    {
        return $this->base()->where('status', ReservationStatus::PendingPayment->value)->where('hold_expires_at', '>', now())->count();
    }

    /** ใช้เส้นทางที่เปิดก่อน ถ้าไม่มี (เช่นถูกฝังในหน้าอื่น) ดูจากโปรไฟล์ที่ผู้ใช้มี */
    public static function modeFor(\App\Models\User $user): string
    {
        $mode = match (true) {
            request()->routeIs('trainer.*') => 'trainer',
            request()->routeIs('member.*') => 'trainee',
            default => $user->member ? 'trainee' : 'trainer',
        };

        abort_if($mode === 'trainer' ? $user->trainer === null : $user->member === null, 403);

        return $mode;
    }

    public function render()
    {
        return view('livewire.reservations.reservation-list')->layout('layouts.app');
    }
}
