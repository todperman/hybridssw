<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ข้อยกเว้นรายวันที่ทับกฎใน ScheduleTemplate
 * เช่น ปิดวันหยุดนักขัตฤกษ์ ลดเวลาเปิด หรือเปิดรอบพิเศษนอกตาราง
 */
class ScheduleException extends Model
{
    use HasFactory;

    public const TYPE_CLOSED = 'closed';
    public const TYPE_CUSTOM_HOURS = 'custom_hours';
    public const TYPE_SPECIAL_OPEN = 'special_open';

    protected $fillable = [
        'branch_id',
        'date',
        'type',
        'start_time',
        'end_time',
        'capacity',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_CLOSED => 'ปิดทั้งวัน',
            self::TYPE_CUSTOM_HOURS => 'เปลี่ยนเวลาเปิด-ปิด',
            self::TYPE_SPECIAL_OPEN => 'เปิดรอบพิเศษ',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }
}
