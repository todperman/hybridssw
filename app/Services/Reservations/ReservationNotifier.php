<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationNotice;
use App\Services\Notify\Notifier;
use Illuminate\Support\Collection;

/**
 * ส่งแจ้งเตือนเรื่องการจองให้ผู้เกี่ยวข้อง และสำเนาเข้ากล่องอีเมลผู้ดูแล
 * วิธีส่ง (ส่งทันที จับ error) อยู่ที่ Notifier
 */
class ReservationNotifier extends Notifier
{
    /** @param  iterable<User>  $users */
    public function send(Reservation $reservation, iterable $users, string $title, string $body, ?string $url = null): void
    {
        $this->deliver(
            new ReservationNotice($title, $body, $url, $reservation->reference),
            $users,
            context: ['reservation' => $reservation->reference],
        );
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

    public function urlFor(Reservation $reservation): string
    {
        return url('/reservations/'.$reservation->reference);
    }
}
