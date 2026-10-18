<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'can_book_without_trainer' => false,
    ];

    protected $fillable = [
        'user_id',
        'branch_id',
        'primary_trainer_id',
        'member_type_id',
        'benefit_expires_at',
        'benefit_note',
        'benefit_claim',
        'member_code',
        'approved_at',
        'approved_by_user_id',
        'review_note',
        'can_book_without_trainer',
        'date_of_birth',
        'gender',
        'emergency_contact_name',
        'emergency_contact_phone',
        'health_note',
        'parq_answers',
        'parq_signed_at',
        'parq_signature',
        'status',
        'suspended_until',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'approved_at' => 'datetime',
            'benefit_expires_at' => 'date',
            'can_book_without_trainer' => 'boolean',
            'date_of_birth' => 'date',
            'parq_answers' => 'array',
            'parq_signed_at' => 'datetime',
            'suspended_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $member) {
            $member->member_code ??= 'MB-'.strtoupper(Str::random(6));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function primaryTrainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'primary_trainer_id');
    }

    public function memberType(): BelongsTo
    {
        return $this->belongsTo(MemberType::class);
    }

    /** ได้สิทธิ์ใช้ฟรีอยู่ตอนนี้: มีประเภทที่เปิดใช้ และสิทธิ์ยังไม่หมดอายุ */
    public function hasBenefit(): bool
    {
        $type = $this->memberType;

        return $type !== null
            && $type->is_active
            && ($this->benefit_expires_at === null || $this->benefit_expires_at->endOfDay()->isFuture());
    }

    /** ป้ายสั้น ๆ เช่น "บุคลากร รพ. · ถึง 31/12/2026" */
    public function benefitLabel(): ?string
    {
        if (! $this->memberType) {
            return null;
        }

        $label = $this->memberType->name;

        if ($this->benefit_expires_at) {
            $label .= ($this->hasBenefit() ? ' · ถึง ' : ' · หมดอายุ ').$this->benefit_expires_at->format('d/m/Y');
        }

        return $label;
    }

    /** ชั่วโมงฟรีที่ใช้ไปแล้วในเดือนนั้น นับจากการจองที่ยังถือเวลาอยู่ (รอชำระหรือยืนยัน) */
    public function freeHoursUsedIn(\Carbon\CarbonInterface $month): int
    {
        return (int) $this->reservations()
            ->whereIn('reservations.status', \App\Enums\ReservationStatus::holding())
            ->whereBetween('reservations.starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->sum('reservation_participants.free_hours');
    }

    /** ชั่วโมงฟรีที่เหลือใช้ได้ในเดือนนั้น null = ไม่จำกัด 0 = หมดหรือไม่มีสิทธิ์ */
    public function freeHoursLeftIn(\Carbon\CarbonInterface $month): ?int
    {
        if (! $this->hasBenefit()) {
            return 0;
        }

        $quota = $this->memberType->free_hours_per_month;

        return $quota === null ? null : max(0, $quota - $this->freeHoursUsedIn($month));
    }

    /**
     * กำหนดหรือถอนประเภทสมาชิก (สิทธิ์ใช้ฟรี) ทำได้เฉพาะแอดมินและต้องมีเหตุผล
     * เก็บประวัติว่าใครเปลี่ยน เมื่อไร เพราะอะไร เหมือนสิทธิ์ไม่มี Trainer
     */
    public function setMemberType(?MemberType $type, ?\Carbon\CarbonInterface $expiresAt, User $admin, string $reason): void
    {
        $before = [
            'member_type' => $this->memberType?->name,
            'benefit_expires_at' => $this->benefit_expires_at?->toDateString(),
        ];

        $this->update([
            'member_type_id' => $type?->id,
            'benefit_expires_at' => $type ? $expiresAt?->toDateString() : null,
            'benefit_note' => $reason,
        ]);

        $this->unsetRelation('memberType');

        AuditLog::record('member.type_changed', $this, $before, [
            'member_type' => $type?->name,
            'benefit_expires_at' => $type ? $expiresAt?->toDateString() : null,
        ], $reason, $admin);
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'team_members')
            ->withPivot(['status', 'joined_at', 'left_at', 'joined_via'])
            ->withTimestamps()
            ->wherePivot('status', 'active');
    }

    /** กลุ่มที่ลูกทีมคนนี้สังกัดอยู่ อยู่ได้มากกว่าหนึ่งกลุ่ม */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_members')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /** เซ็นแบบคัดกรองสุขภาพแล้วหรือยัง */
    public function hasSignedParq(): bool
    {
        return $this->parq_signed_at !== null;
    }

    /** แอดมินระงับไว้หรือไม่ (หมดเวลาระงับแล้วถือว่าปกติ) */
    public function isSuspended(): bool
    {
        if ($this->status === MemberStatus::Suspended) {
            return $this->suspended_until === null || $this->suspended_until->isFuture();
        }

        return false;
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_participants')->withTimestamps();
    }

    /** เพิ่มชื่อในการจองได้: อนุมัติการสมัครแล้ว สถานะปกติ และไม่ถูกระงับ */
    public function canJoinReservations(): bool
    {
        return $this->isActive() && ! $this->awaitsApproval();
    }

    /** สมัครเองแล้วยังไม่ผ่านการอนุมัติ */
    public function awaitsApproval(): bool
    {
        return $this->status->awaitsApproval();
    }

    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * ให้หรือถอนสิทธิ์เข้าใช้โดยไม่มี Trainer ทำได้เฉพาะแอดมิน และต้องมีเหตุผลเสมอ
     * เก็บประวัติว่าใครเปลี่ยน เมื่อไร เพราะอะไร ตามข้อกำหนดข้อ 2
     */
    public function setNoTrainerPrivilege(bool $granted, User $admin, string $reason): void
    {
        if ((bool) $this->can_book_without_trainer === $granted) {
            return;
        }

        $before = ['can_book_without_trainer' => (bool) $this->can_book_without_trainer];

        $this->update(['can_book_without_trainer' => $granted]);

        AuditLog::record('member.no_trainer_privilege', $this, $before, ['can_book_without_trainer' => $granted], $reason, $admin);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->latest('id');
    }

    /** แอดมินอนุมัติการสมัคร จองได้ทันทีหลังจากนี้ */
    public function approve(?User $by = null): void
    {
        $this->update([
            'status' => MemberStatus::Active,
            'approved_at' => now(),
            'approved_by_user_id' => $by?->id,
            'review_note' => null,
        ]);

        AuditLog::record('member.approved', $this, null, null, null, $by);
        app(\App\Services\Notify\AccountNotifier::class)->memberApproved($this);
    }

    /** แอดมินไม่อนุมัติ เหตุผลจะแสดงให้สมาชิกเห็นที่หน้าสถานะการสมัคร */
    public function reject(?User $by, string $note): void
    {
        $this->update([
            'status' => MemberStatus::Rejected,
            'approved_at' => null,
            'approved_by_user_id' => $by?->id,
            'review_note' => $note,
        ]);

        AuditLog::record('member.rejected', $this, null, null, $note, $by);
        app(\App\Services\Notify\AccountNotifier::class)->memberRejected($this, $note);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active && ! $this->isSuspended();
    }

    public function scopeActive($query)
    {
        return $query->where('status', MemberStatus::Active->value);
    }
}
