<?php

namespace App\Models;

use App\Enums\BookingStatus;
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

    protected $fillable = [
        'user_id',
        'branch_id',
        'primary_trainer_id',
        'member_code',
        'date_of_birth',
        'gender',
        'emergency_contact_name',
        'emergency_contact_phone',
        'health_note',
        'parq_answers',
        'parq_signed_at',
        'parq_signature',
        'status',
        'no_show_count',
        'no_show_reset_at',
        'suspended_until',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'date_of_birth' => 'date',
            'parq_answers' => 'array',
            'parq_signed_at' => 'datetime',
            'no_show_count' => 'integer',
            'no_show_reset_at' => 'datetime',
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

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'team_members')
            ->withPivot(['status', 'joined_at', 'left_at', 'joined_via'])
            ->withTimestamps()
            ->wherePivot('status', 'active');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** กลุ่มที่ลูกทีมคนนี้สังกัดอยู่ อยู่ได้มากกว่าหนึ่งกลุ่ม */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_members')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function packages(): HasMany
    {
        return $this->hasMany(MemberPackage::class);
    }

    /** เซ็นแบบคัดกรองสุขภาพแล้วหรือยัง */
    public function hasSignedParq(): bool
    {
        return $this->parq_signed_at !== null;
    }

    /** ถูกระงับสิทธิ์จองอยู่หรือไม่ (หมดเวลาระงับแล้วถือว่าปกติ) */
    public function isSuspended(): bool
    {
        if ($this->status === MemberStatus::Suspended) {
            return $this->suspended_until === null || $this->suspended_until->isFuture();
        }

        return false;
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active && ! $this->isSuspended();
    }

    /** เครดิตคงเหลือจากทุกแพ็กเกจที่ยังไม่หมดอายุ */
    public function availableCredits(): int
    {
        return $this->packages()
            ->active()
            ->get()
            ->sum(fn (MemberPackage $p) => $p->creditsRemaining());
    }

    /** การจองที่ยังใช้ที่นั่งอยู่ในอนาคต */
    public function upcomingBookings(): HasMany
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::Booked->value, BookingStatus::Waitlisted->value])
            ->whereHas('workoutSession', fn ($q) => $q->where('starts_at', '>=', now()));
    }

    public function scopeActive($query)
    {
        return $query->where('status', MemberStatus::Active->value);
    }
}
