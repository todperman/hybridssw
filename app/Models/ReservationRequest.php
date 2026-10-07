<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** คำขอที่ต้องให้แอดมินตัดสิน: เลื่อนหลังเส้นตาย หรือ Trainer ขอยกเลิกการรับงาน */
class ReservationRequest extends Model
{
    protected $fillable = [
        'reservation_id', 'type', 'requested_by_user_id', 'reason',
        'new_starts_at', 'new_ends_at', 'replacement_trainer_id',
        'status', 'reviewed_by_user_id', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'new_starts_at' => 'datetime',
            'new_ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function replacementTrainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'replacement_trainer_id');
    }
}
