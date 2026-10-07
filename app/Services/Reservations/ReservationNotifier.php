<?php

namespace App\Services\Reservations;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ส่งแจ้งเตือนเรื่องการจองให้ผู้เกี่ยวข้อง
 *
 * ส่งทันทีไม่เข้าคิว เพราะเซิร์ฟเวอร์ไม่มีตัวรันคิว และถ้าส่งเมลพังต้องไม่ทำให้การจองพัง
 * จึงจับ error ไว้แล้วบันทึก log แทน การจองสำเร็จสำคัญกว่าแจ้งเตือนหนึ่งฉบับ
 */
class ReservationNotifier
{
    /** @param  iterable<User>  $users */
    public function send(Reservation $reservation, iterable $users, string $title, string $body, ?string $url = null): void
    {
        $notice = new ReservationNotice($title, $body, $url, $reservation->reference);

        collect($users)
            ->filter()
            ->unique('id')
            ->each(function (User $user) use ($notice, $reservation) {
                try {
                    $user->notify($notice);
                } catch (\Throwable $e) {
                    Log::warning('ส่งแจ้งเตือนการจองไม่สำเร็จ', [
                        'reservation' => $reservation->reference,
                        'user' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    /** ผู้เข้าร่วมทุกคนกับ Trainer ของการจอง */
    public function everyone(Reservation $reservation): Collection
    {
        $reservation->loadMissing('participants.user', 'trainer.user', 'createdBy');

        return $reservation->participants->pluck('user')
            ->push($reservation->trainer?->user)
            ->push($reservation->createdBy)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function payer(Reservation $reservation): Collection
    {
        return collect([$reservation->payer?->user])->filter();
    }

    /** แอดมินและเจ้าหน้าที่ของสาขา รวมคนที่ดูแลทุกสาขา */
    public function admins(Branch $branch): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->role?->canAccessAdminPanel()
                && ($u->branch_id === null || $u->branch_id === $branch->id || $u->role->isGlobal()))
            ->values();
    }

    public function urlFor(Reservation $reservation): string
    {
        return url('/reservations/'.$reservation->reference);
    }
}
