<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;

/** ตัวช่วยสำหรับเทสต์ระบบจอง Private Gym ต่อยอดจาก BuildsGym */
trait BuildsReservations
{
    use BuildsGym;

    /** สาขาที่ตั้งราคาแล้ว เปิดทุกวัน 06:00–22:00 */
    protected function makeGym(array $attributes = []): Branch
    {
        $branch = $this->makeBranch(array_merge([
            'hourly_rate' => 800,
            'max_trainees' => 6,
            'booking_window_days' => 45,
            'max_booking_hours' => 4,
        ], $attributes));

        foreach (range(0, 6) as $day) {
            $this->makeTemplate($branch, ['day_of_week' => $day, 'start_time' => '06:00', 'end_time' => '22:00']);
        }

        return $branch;
    }

    /** Trainer ที่ตั้งเวลาว่างทุกวัน 06:00–22:00 */
    protected function makeAvailableTrainer(Branch $branch, array $attributes = []): Trainer
    {
        $trainer = $this->makeTrainer($branch, $attributes);

        foreach (range(0, 6) as $day) {
            $trainer->availabilities()->create(['day_of_week' => $day, 'start_time' => '06:00', 'end_time' => '22:00']);
        }

        return $trainer;
    }

    protected function makeAdmin(Branch $branch): User
    {
        return User::create([
            'branch_id' => $branch->id, 'first_name' => 'แอด', 'last_name' => 'มิน',
            'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => UserRole::Admin,
        ])->refresh();
    }

    /** เวลาเริ่มตอน 18:00 ของวันพรุ่งนี้ ตรงช่วงที่ยิมเปิด */
    protected function tomorrowAt(int $hour = 18): CarbonImmutable
    {
        return CarbonImmutable::now()->addDay()->setTime($hour, 0);
    }

    protected function reservations(): ReservationService
    {
        return app(ReservationService::class);
    }

    protected function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    /** สมาชิกจองเองพร้อม Trainer คืนการจองที่รอชำระ */
    protected function bookAsTrainee(Branch $branch, Member $booker, array $others = [], ?Trainer $trainer = null, $start = null, int $hours = 1): Reservation
    {
        $members = array_merge([$booker->id], array_map(fn (Member $m) => $m->id, $others));

        return $this->reservations()->create(
            $branch,
            $start ?? $this->tomorrowAt(),
            $hours,
            $members,
            $booker->id,
            $trainer,
            $booker->user,
            Reservation::VIA_TRAINEE,
        );
    }

    protected function confirmManually(Reservation $reservation, ?User $admin = null): Reservation
    {
        $this->payments()->recordSuccess($reservation, 'manual', $reservation->amount, null, $admin);

        return $reservation->refresh();
    }
}
