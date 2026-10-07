<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** วันหรือช่วงที่ Trainer ไม่รับงาน ทับเวลาว่างประจำสัปดาห์ */
class TrainerTimeOff extends Model
{
    protected $fillable = ['trainer_id', 'date', 'start_time', 'end_time', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function isWholeDay(): bool
    {
        return $this->start_time === null || $this->end_time === null;
    }
}
