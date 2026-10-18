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

        return view('guide', [
            'user' => $user,
            'branch' => $branch,
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
