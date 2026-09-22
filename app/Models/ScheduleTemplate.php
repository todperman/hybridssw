<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * กฎเวลาเปิดประจำสัปดาห์ของสาขา
 * ไม่ได้เก็บรอบจริง แต่เป็นสูตรที่ SessionGenerator ใช้สร้างรอบล่วงหน้า
 */
class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'capacity',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'slot_duration_minutes' => 'integer',
            'capacity' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public const DAY_NAMES = [
        0 => 'อาทิตย์',
        1 => 'จันทร์',
        2 => 'อังคาร',
        3 => 'พุธ',
        4 => 'พฤหัสบดี',
        5 => 'ศุกร์',
        6 => 'เสาร์',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dayName(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? '-';
    }

    /** กฎนี้มีผลกับวันที่ที่ระบุหรือไม่ */
    public function appliesOn(\Carbon\CarbonInterface $date): bool
    {
        if (! $this->is_active || $this->day_of_week !== $date->dayOfWeek) {
            return false;
        }

        if ($this->effective_from && $date->lt($this->effective_from->startOfDay())) {
            return false;
        }

        if ($this->effective_until && $date->gt($this->effective_until->endOfDay())) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
