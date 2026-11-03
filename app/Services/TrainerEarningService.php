<?php

namespace App\Services;

use App\Enums\EarningStatus;
use App\Enums\ReservationStatus;
use App\Enums\TrainerType;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\TrainerEarning;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Services\Notify\Notifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ส่วนแบ่งเทรนเนอร์
 *
 * กติกา (เจ้าของระบบ 2026-11-03):
 * - ค่า Trainer เป็นจำนวนเงินคงที่ต่อชั่วโมงตามจำนวนคน (PayoutTable ของสาขา) × จำนวนชั่วโมง
 * - สิทธิ์ฟรีหรือโค้ดส่วนลดของลูกค้าไม่ลดส่วนแบ่ง (ยิมรับส่วนลดเอง) จึงคิดจากจำนวนคน ไม่ใช่จากยอดที่จ่าย
 * - เทรนเนอร์ภายใน (พนักงาน) ได้ 0 ไม่สร้างแถว
 * - นับเป็นรายได้ (payable) เมื่อหมดเวลาใช้งานจริง ยกเลิก/คืนเงิน = void ยกเว้นที่จ่ายไปแล้ว (คงไว้ ลงประวัติให้แอดมินตามเอง)
 *
 * sync() ถูกเรียกทุกครั้งที่การจองเปลี่ยน (ยืนยัน เลื่อน เปลี่ยน Trainer ยกเลิก) และตัดสินใจจากสภาพปัจจุบันของการจอง
 */
class TrainerEarningService
{
    public function __construct(protected Notifier $notify) {}

    /** คำนวณและบันทึกให้ตรงกับสภาพปัจจุบันของการจอง คืนแถว (หรือ null เมื่อไม่มีส่วนแบ่ง) */
    public function sync(Reservation $reservation): ?TrainerEarning
    {
        $reservation->loadMissing('trainer', 'branch');
        $existing = TrainerEarning::where('reservation_id', $reservation->id)->first();
        $trainer = $reservation->trainer;
        $eligible = $reservation->status === ReservationStatus::Confirmed && $trainer && $this->eligible($trainer);

        if (! $eligible) {
            if ($existing && $existing->status !== EarningStatus::Void && $existing->status !== EarningStatus::Paid) {
                $existing->update(['status' => EarningStatus::Void, 'void_reason' => $this->voidReason($reservation)]);
            } elseif ($existing && $existing->status === EarningStatus::Paid && $reservation->status !== ReservationStatus::Confirmed) {
                // จ่ายไปแล้วแต่การจองถูกยกเลิกทีหลัง ลงประวัติให้แอดมินหักรอบถัดไปเอง
                AuditLog::record('earning.paid_but_cancelled', $existing, null, ['amount' => (string) $existing->amount], $this->voidReason($reservation));
            }

            return null;
        }

        $people = $reservation->participants()->count();
        $split = $reservation->branch->payoutTable()->split($people);
        $hours = (int) $reservation->hours;
        $values = [
            'trainer_id' => $trainer->id,
            'branch_id' => $reservation->branch_id,
            'people' => $people,
            'hours' => $hours,
            'per_hour' => $split['trainer'],
            'amount' => round($split['trainer'] * $hours, 2),
            'facility_amount' => round($split['facility'] * $hours, 2),
            'customer_amount' => (float) $reservation->amount,
            'earned_at' => $reservation->ends_at,
        ];

        if (! $existing) {
            return TrainerEarning::create($values + ['reservation_id' => $reservation->id, 'status' => $this->statusFor($reservation->ends_at)]);
        }

        if ($existing->status === EarningStatus::Paid) {
            // จ่ายแล้ว ไม่แก้ยอดย้อนหลัง แต่ถ้าเปลี่ยน Trainer ต้องให้แอดมินรู้
            if ($existing->trainer_id !== $trainer->id) {
                AuditLog::record('earning.paid_but_reassigned', $existing, ['trainer_id' => $existing->trainer_id], ['trainer_id' => $trainer->id], 'เปลี่ยน Trainer หลังจ่ายส่วนแบ่งแล้ว');
            }

            return $existing;
        }

        $existing->fill($values + ['status' => $this->statusFor($reservation->ends_at), 'void_reason' => null])->save();

        return $existing;
    }

    /** ย้าย accrued → payable เมื่อเวลาใช้งานผ่านไปแล้ว เรียกจาก scheduler และทุกครั้งที่เปิดหน้ารายได้ */
    public function settle(): int
    {
        return TrainerEarning::where('status', EarningStatus::Accrued->value)
            ->where('earned_at', '<=', now())
            ->update(['status' => EarningStatus::Payable->value]);
    }

    /**
     * จ่ายรอบ: ทุกแถว payable ของเทรนเนอร์ที่หมดเวลาในช่วงนี้ → paid พร้อมบันทึกและแจ้งเทรนเนอร์
     *
     * @return array{count: int, amount: float}
     */
    public function pay(Trainer $trainer, CarbonInterface $from, CarbonInterface $to, User $admin, ?string $note = null): array
    {
        $this->settle();

        $rows = TrainerEarning::where('trainer_id', $trainer->id)
            ->where('status', EarningStatus::Payable->value)
            ->whereBetween('earned_at', [$from, $to])
            ->get();

        if ($rows->isEmpty()) {
            return ['count' => 0, 'amount' => 0.0];
        }

        $amount = (float) $rows->sum('amount');

        DB::transaction(function () use ($rows, $admin, $note) {
            TrainerEarning::whereIn('id', $rows->modelKeys())->update([
                'status' => EarningStatus::Paid->value,
                'paid_at' => now(),
                'paid_by_user_id' => $admin->id,
                'payout_note' => $note,
            ]);
        });

        AuditLog::record('earning.paid', $trainer, null, [
            'count' => $rows->count(),
            'amount' => number_format($amount, 2, '.', ''),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ], $note, $admin);

        $this->notify->deliver(
            new AccountNotice(
                'จ่ายส่วนแบ่งแล้ว ฿'.number_format($amount),
                'รอบ '.$from->locale('th')->isoFormat('D MMM').' – '.$to->locale('th')->isoFormat('D MMM YYYY').' จำนวน '.$rows->count().' งาน รวม '.number_format($amount, 2).' บาท'.($note ? ' · '.$note : ''),
                route('trainer.earnings'),
            ),
            [$trainer->user],
            context: ['trainer' => $trainer->id],
        );

        return ['count' => $rows->count(), 'amount' => $amount];
    }

    /** ยอดสรุปของชุดแถวที่ให้มา (ใช้ทั้งหน้าแอดมินและหน้าเทรนเนอร์) */
    public static function summarize(Collection $rows): array
    {
        $counting = $rows->filter(fn (TrainerEarning $e) => $e->counts());

        return [
            'jobs' => $counting->count(),
            'hours' => (int) $counting->sum('hours'),
            'amount' => (float) $counting->sum('amount'),
            'customer' => (float) $counting->sum('customer_amount'),
            'facility' => (float) $counting->sum('facility_amount'),
            'paid' => (float) $counting->where('status', EarningStatus::Paid)->sum('amount'),
            'payable' => (float) $counting->where('status', EarningStatus::Payable)->sum('amount'),
            'accrued' => (float) $counting->where('status', EarningStatus::Accrued)->sum('amount'),
        ];
    }

    public function eligible(Trainer $trainer): bool
    {
        return $trainer->type === TrainerType::External;
    }

    protected function statusFor(CarbonInterface $endsAt): EarningStatus
    {
        return $endsAt->lte(now()) ? EarningStatus::Payable : EarningStatus::Accrued;
    }

    protected function voidReason(Reservation $reservation): string
    {
        return match (true) {
            $reservation->status === ReservationStatus::Cancelled => 'การจองถูกยกเลิก',
            $reservation->status === ReservationStatus::Expired => 'การจองหมดอายุ',
            $reservation->trainer === null => 'การจองไม่มี Trainer แล้ว',
            ! $this->eligible($reservation->trainer) => 'เทรนเนอร์ภายในไม่มีส่วนแบ่ง',
            default => 'การจองไม่อยู่ในสถานะยืนยัน',
        };
    }
}
