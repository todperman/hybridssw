<?php

namespace App\Models;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Trainer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'branch_id',
        'code',
        'type',
        'status',
        'bio',
        'specialties',
        'certification_name',
        'certification_file_path',
        'certification_expires_at',
        'contract_starts_at',
        'contract_ends_at',
        'max_seats_per_session',
        'advance_booking_days',
        'max_team_size',
        'invite_token',
        'approved_at',
        'approved_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrainerType::class,
            'status' => TrainerStatus::class,
            'specialties' => 'array',
            'certification_expires_at' => 'date',
            'contract_starts_at' => 'date',
            'contract_ends_at' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $trainer) {
            $trainer->code ??= 'TR-'.strtoupper(Str::random(6));
            $trainer->invite_token ??= Str::random(40);
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** ลูกทีมที่ยังอยู่ในทีม */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_members')
            ->withPivot(['status', 'joined_at', 'left_at', 'joined_via', 'trainer_note'])
            ->withTimestamps()
            ->wherePivot('status', 'active');
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** กลุ่มลูกทีมที่เทรนเนอร์คนนี้สร้างไว้ */
    public function memberGroups(): HasMany
    {
        return $this->hasMany(MemberGroup::class);
    }

    // โควตา: ใช้ค่าเฉพาะรายถ้าตั้งไว้ ไม่งั้นถอยไปใช้ค่าตามประเภทเทรนเนอร์

    public function maxSeatsPerSession(): int
    {
        return $this->max_seats_per_session ?? $this->type->defaultMaxSeatsPerSession();
    }

    public function advanceBookingDays(): int
    {
        return $this->advance_booking_days ?? $this->type->defaultAdvanceBookingDays();
    }

    /** null แปลว่าไม่จำกัดจำนวนลูกทีม */
    /**
     * เพดานจำนวนลูกทีม คืน null = ไม่จำกัด
     *
     * ค่าในคอลัมน์มีสามความหมาย
     *   null = ใช้ค่ามาตรฐานตามประเภทเทรนเนอร์
     *   0    = ตั้งใจให้ไม่จำกัดเฉพาะคนนี้ แม้ประเภทจะมีเพดานก็ตาม
     *   > 0  = เพดานที่กำหนดเอง
     *
     * ใช้ 0 เป็นตัวแทนของ "ไม่จำกัด" เพราะทีมขนาด 0 คนไม่มีความหมายอยู่แล้ว
     * และทำให้ยังแยกออกจาก null ที่แปลว่า "ตามค่ามาตรฐาน" ได้
     */
    public function maxTeamSize(): ?int
    {
        if ($this->max_team_size === null) {
            return $this->type->defaultMaxTeamSize();
        }

        return (int) $this->max_team_size === 0 ? null : (int) $this->max_team_size;
    }

    public function hasUnlimitedTeam(): bool
    {
        return $this->maxTeamSize() === null;
    }

    /** ข้อความแสดงเพดานทีม ใช้ร่วมกันหลายหน้า */
    public function teamLimitLabel(): string
    {
        return $this->hasUnlimitedTeam() ? 'ไม่จำกัด' : (string) $this->maxTeamSize();
    }

    public function isApproved(): bool
    {
        return $this->status === TrainerStatus::Approved;
    }

    /** ใบรับรองหมดอายุแล้วหรือยัง (เฉพาะเทรนเนอร์ภายนอกที่บังคับ) */
    public function hasCertificationExpired(): bool
    {
        if (! $this->type->requiresCertification()) {
            return false;
        }

        return $this->certification_expires_at !== null
            && $this->certification_expires_at->isPast();
    }

    /** สัญญายังมีผลอยู่หรือไม่ */
    public function isContractActive(): bool
    {
        $today = now()->toDateString();

        if ($this->contract_starts_at && $this->contract_starts_at->toDateString() > $today) {
            return false;
        }

        if ($this->contract_ends_at && $this->contract_ends_at->toDateString() < $today) {
            return false;
        }

        return true;
    }

    public function inviteUrl(): string
    {
        return route('team.join', ['token' => $this->invite_token]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', TrainerStatus::Approved->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', TrainerStatus::Pending->value);
    }
}
