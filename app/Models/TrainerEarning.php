<?php

namespace App\Models;

use App\Enums\EarningStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ส่วนแบ่งเทรนเนอร์ของการจองหนึ่งรายการ (ดู TrainerEarningService) */
class TrainerEarning extends Model
{
    protected $fillable = [
        'reservation_id', 'trainer_id', 'branch_id', 'people', 'hours', 'per_hour', 'amount', 'facility_amount',
        'customer_amount', 'status', 'earned_at', 'paid_at', 'paid_by_user_id', 'payout_note', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => EarningStatus::class,
            'earned_at' => 'datetime',
            'paid_at' => 'datetime',
            'per_hour' => 'decimal:2',
            'amount' => 'decimal:2',
            'facility_amount' => 'decimal:2',
            'customer_amount' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function counts(): bool
    {
        return $this->status !== EarningStatus::Void;
    }

    public function scopeCounting($query)
    {
        return $query->whereIn('status', EarningStatus::counting());
    }
}
