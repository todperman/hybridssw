<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RequestStatus;
use App\Enums\ReservationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * การจอง Private Gym หนึ่งรายการ ใช้ทั้งยิมตลอดช่วงเวลา
 * แก้สถานะผ่าน ReservationService เท่านั้น เพราะต้องจัดการ slot_locks ไปพร้อมกัน
 */
class Reservation extends Model
{
    public const VIA_TRAINER = 'trainer';
    public const VIA_TRAINEE = 'trainee';
    public const VIA_ADMIN = 'admin';

    protected $fillable = [
        'reference', 'branch_id', 'trainer_id', 'payer_member_id', 'created_by_user_id', 'created_via',
        'starts_at', 'ends_at', 'hours', 'status', 'hold_expires_at',
        'hourly_rate', 'amount', 'currency',
        'confirmed_at', 'expired_at', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason',
        'refund_status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'hours' => 'integer',
            'hourly_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => ReservationStatus::class,
            'refund_status' => RefundStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $r) {
            $r->reference ??= 'HS'.now()->format('ymd').strtoupper(Str::random(5));
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payer_member_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'reservation_participants')->withTimestamps();
    }

    public function locks(): HasMany
    {
        return $this->hasMany(SlotLock::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ReservationRequest::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->latest('id');
    }

    public function successfulPayment(): ?Payment
    {
        return $this->payments()->where('status', PaymentStatus::Succeeded->value)->latest('id')->first();
    }

    public function pendingRequest(): ?ReservationRequest
    {
        return $this->requests()->where('status', RequestStatus::Pending->value)->latest('id')->first();
    }

    /**
     * ชั่วโมงที่การจองนี้ใช้ ทีละช่วง 1 ชั่วโมง
     *
     * @return array<int, \Carbon\CarbonImmutable>
     */
    public function slots(): array
    {
        return static::slotsBetween($this->starts_at, $this->hours);
    }

    /** @return array<int, \Carbon\CarbonImmutable> */
    public static function slotsBetween(CarbonInterface $start, int $hours): array
    {
        $start = \Carbon\CarbonImmutable::parse($start);

        return array_map(fn (int $i) => $start->addHours($i), range(0, $hours - 1));
    }

    /**
     * เส้นตายเลื่อนเองคือ 00.00 น. ของวันใช้งาน
     * จองวันที่ 10 เวลา 18.00 ต้องเลื่อนให้เสร็จภายใน 9 เวลา 23.59
     */
    public function rescheduleDeadline(): CarbonInterface
    {
        return $this->starts_at->copy()->startOfDay();
    }

    public function canRescheduleWithoutApproval(): bool
    {
        return now()->lt($this->rescheduleDeadline());
    }

    public function isParticipant(Member $member): bool
    {
        return $this->participants()->whereKey($member->id)->exists();
    }

    public function timeLabel(): string
    {
        return $this->starts_at->format('H:i').' - '.$this->ends_at->format('H:i');
    }

    public function dateLabel(): string
    {
        return $this->starts_at->locale('th')->isoFormat('ddd D MMM YYYY');
    }

    public function scopeHolding($query)
    {
        return $query->whereIn('status', ReservationStatus::holding());
    }
}
