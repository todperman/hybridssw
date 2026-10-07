<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ช่วงเวลาที่ Trainer พร้อมรับงาน ประจำสัปดาห์ (day_of_week) หรือเฉพาะวัน (date) */
class TrainerAvailability extends Model
{
    protected $fillable = ['trainer_id', 'day_of_week', 'date', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'date' => 'date',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function isWeekly(): bool
    {
        return $this->date === null;
    }

    public function label(): string
    {
        $when = $this->isWeekly()
            ? 'ทุกวัน'.(ScheduleTemplate::DAY_NAMES[$this->day_of_week] ?? '')
            : $this->date->locale('th')->isoFormat('ddd D MMM');

        return $when.' '.substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }
}
