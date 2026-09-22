<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'workout_session_id',
        'member_id',
        'trainer_id',
        'booked_by_user_id',
        'status',
        'waitlist_position',
        'promoted_at',
        'confirm_deadline_at',
        'checked_in_at',
        'checked_in_by',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'cancelled_late',
        'member_package_id',
        'credit_consumed',
        'notes',
        'active_member_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'waitlist_position' => 'integer',
            'promoted_at' => 'datetime',
            'confirm_deadline_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_late' => 'boolean',
            'credit_consumed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->reference ??= 'BK'.now()->format('ymd').strtoupper(Str::random(5));
        });
    }

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function memberPackage(): BelongsTo
    {
        return $this->belongsTo(MemberPackage::class);
    }

    /** ยกเลิกตอนนี้จะถือว่าเลยกำหนดและโดนหักเครดิตหรือไม่ */
    public function wouldBeLateCancellation(): bool
    {
        $session = $this->workoutSession;
        $cutoffHours = $session->branch->cancellation_cutoff_hours;

        return now()->diffInMinutes($session->starts_at, false) < $cutoffHours * 60;
    }

    /** คิวสำรองที่ได้เลื่อนขึ้นแล้วแต่ยังไม่ยืนยันจนหมดเวลา */
    public function hasExpiredConfirmation(): bool
    {
        return $this->confirm_deadline_at !== null
            && $this->confirm_deadline_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            BookingStatus::Booked->value,
            BookingStatus::CheckedIn->value,
            BookingStatus::Completed->value,
        ]);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereHas('workoutSession', fn ($q) => $q->where('starts_at', '>=', now()));
    }
}
