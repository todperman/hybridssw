<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\MemberType;
use App\Models\User;
use App\Services\Payments\Omise\PaymentMethods;

/**
 * ข้อมูลที่คู่มือใช้ ทั้งหน้าเว็บและ PDF ดึงจากที่เดียวกันเพื่อให้ตัวเลขตรงกันเสมอ
 * (กติกาการจอง ช่องทางชำระ ตารางราคาของสาขา ประเภทสมาชิก)
 */
class GuideData
{
    /** @return array<string, mixed> */
    public static function for(User $user): array
    {
        $isAdmin = (bool) $user->role?->canAccessAdminPanel();
        $branch = $user->member?->branch ?? $user->trainer?->branch ?? $user->branch ?? Branch::query()->where('is_active', true)->first();

        $omise = config('gym.payments.driver') === 'omise';
        $configured = (array) config('services.omise.methods', []);
        $methods = $omise
            ? collect(PaymentMethods::all())->only($configured ?: array_keys(PaymentMethods::all()))
            : collect();

        $table = $branch?->priceTable();
        $maxPeople = max(1, (int) ($branch?->max_trainees ?? 5));
        $priceRows = $table ? collect(range(1, $maxPeople))->map(fn (int $n) => [
            'people' => $n,
            'with' => $table->perHour($n, true),
            'without' => $table->perHour($n, false),
        ])->all() : [];
        $examplePeople = min(3, $maxPeople);
        $example = $table ? [
            'people' => $examplePeople,
            'total' => $table->perHour($examplePeople, true),
            'share' => round($table->perHour($examplePeople, true) / $examplePeople),
        ] : null;

        return [
            'user' => $user,
            'branch' => $branch,
            'priceRows' => $priceRows,
            'example' => $example,
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'isAdmin' => $isAdmin,
            'showMember' => $isAdmin || $user->member !== null || $user->trainer === null,
            'showTrainer' => $isAdmin || $user->trainer !== null,
            'hasBenefit' => (bool) $user->member?->hasBenefit(),
            'omise' => $omise,
            'methods' => $methods,
            'holdMinutes' => (int) config('gym.reservation.hold_minutes', 30),
            'reminderLead' => (int) config('gym.reminder.lead_minutes', 90),
            'noTrainerRule' => config('gym.reservation.no_trainer_rule', 'all'),
            'approvalNeeded' => RegistrationRules::memberRegistrationNeedsApproval(),
            'maxHours' => max(1, (int) ($branch?->max_booking_hours ?? 1)),
            'window' => (int) ($branch?->booking_window_days ?? 30),
            'maxPeople' => $maxPeople,
        ];
    }
}
