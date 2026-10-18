<?php

namespace App\Http\Controllers;

use App\Services\Payments\Omise\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * คู่มือการใช้งานสำหรับคนที่ล็อกอินแล้ว เนื้อหาเปลี่ยนตามบทบาทและดึงกติกาจริงจากการตั้งค่า
 * (ชั่วโมงสูงสุด ระยะจองล่วงหน้า เวลารอชำระ ช่องทางชำระ ราคาของสาขา) จะได้ไม่ต้องแก้คู่มือทุกครั้งที่ตั้งค่าเปลี่ยน
 */
class GuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isAdmin = (bool) $user->role?->canAccessAdminPanel();
        $branch = $user->member?->branch ?? $user->trainer?->branch ?? $user->branch ?? \App\Models\Branch::query()->where('is_active', true)->first();

        $omise = config('gym.payments.driver') === 'omise';
        $configured = (array) config('services.omise.methods', []);
        $methods = $omise
            ? collect(PaymentMethods::all())->only($configured ?: array_keys(PaymentMethods::all()))
            : collect();

        // ข้อมูลสำหรับอินโฟกราฟิก: ราคาต่อชั่วโมงตามจำนวนคน (ทั้งสองแบบ) และตัวอย่างสิทธิ์ฟรี 3 คน
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

        return view('guide', [
            'user' => $user,
            'branch' => $branch,
            'priceRows' => $priceRows,
            'example' => $example,
            'memberTypes' => \App\Models\MemberType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'isAdmin' => $isAdmin,
            'showMember' => $isAdmin || $user->member !== null || $user->trainer === null,
            'showTrainer' => $isAdmin || $user->trainer !== null,
            'hasBenefit' => (bool) $user->member?->hasBenefit(),
            'omise' => $omise,
            'methods' => $methods,
            'holdMinutes' => (int) config('gym.reservation.hold_minutes', 30),
            'reminderLead' => (int) config('gym.reminder.lead_minutes', 90),
            'noTrainerRule' => config('gym.reservation.no_trainer_rule', 'all'),
            'approvalNeeded' => \App\Support\RegistrationRules::memberRegistrationNeedsApproval(),
        ]);
    }
}
