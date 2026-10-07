<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ประวัติการเปลี่ยนแปลงที่ข้อกำหนดบังคับให้เก็บ: ผู้ทำ วันเวลา เหตุผล ข้อมูลก่อนและหลัง
 * ใช้กับการเลื่อน การเปลี่ยน Trainer การเปลี่ยนสิทธิ์พิเศษ และการคืนเงิน
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_user_id', 'action', 'subject_type', 'subject_id', 'reason', 'before', 'after'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?User $actor = null,
    ): self {
        return static::create([
            'actor_user_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ]);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'reservation.rescheduled' => 'เลื่อนวันเวลา',
            'reservation.trainer_changed' => 'เปลี่ยน Trainer',
            'reservation.cancelled' => 'ยกเลิกการจอง',
            'reservation.payment_recorded' => 'บันทึกรับชำระ',
            'refund.created' => 'สร้างรายการคืนเงิน',
            'refund.updated' => 'อัปเดตผลคืนเงิน',
            'request.approved' => 'อนุมัติคำขอ',
            'request.rejected' => 'ไม่อนุมัติคำขอ',
            'member.no_trainer_privilege' => 'เปลี่ยนสิทธิ์เข้าใช้โดยไม่มี Trainer',
            default => $this->action,
        };
    }
}
