<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * รอบการเล่นจริง 1 แถว = 1 ชั่วโมงของสาขาหนึ่ง
 * booked_count เป็นตัวนับที่ถือว่าเชื่อถือได้ และถูกแก้ภายใต้ row lock ใน BookingService เท่านั้น
 */
class WorkoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'schedule_template_id',
        'date',
        'starts_at',
        'ends_at',
        'capacity',
        'booked_count',
        'waitlist_count',
        'mode',
        'claimed_by_trainer_id',
        'status',
        'close_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'waitlist_count' => 'integer',
            'mode' => SessionMode::class,
            'status' => SessionStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function claimedByTrainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'claimed_by_trainer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** การจองที่ยังกินที่นั่งอยู่ */
    public function activeBookings(): HasMany
    {
        return $this->bookings()->whereIn('status', [
            BookingStatus::Booked->value,
            BookingStatus::CheckedIn->value,
            BookingStatus::Completed->value,
        ]);
    }

    public function waitlist(): HasMany
    {
        return $this->bookings()
            ->where('status', BookingStatus::Waitlisted->value)
            ->orderBy('waitlist_position');
    }

    public function seatsRemaining(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    public function isFull(): bool
    {
        return $this->booked_count >= $this->capacity;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    /** เปิดให้จองอยู่จริง ณ ตอนนี้ */
    public function isBookable(): bool
    {
        return $this->status->acceptsBookings() && ! $this->hasStarted();
    }

    /** เทรนเนอร์รายนี้ถูกกันไม่ให้จองเพราะรอบถูกเหมาไปแล้วหรือไม่ */
    public function isLockedForTrainer(Trainer $trainer): bool
    {
        return $this->mode === SessionMode::Exclusive
            && $this->claimed_by_trainer_id !== null
            && $this->claimed_by_trainer_id !== $trainer->id;
    }

    /** จำนวนที่นั่งที่เทรนเนอร์รายนี้ใช้ไปแล้วในรอบนี้ */
    public function seatsUsedByTrainer(Trainer $trainer): int
    {
        return $this->activeBookings()->where('trainer_id', $trainer->id)->count();
    }

    public function timeLabel(): string
    {
        return $this->starts_at->format('H:i').' - '.$this->ends_at->format('H:i');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now());
    }

    public function scopeOpen($query)
    {
        return $query->where('status', SessionStatus::Open->value);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }
}
