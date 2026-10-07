<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ล็อกทรัพยากรหนึ่งชิ้นหนึ่งชั่วโมง unique(resource, resource_id, slot_start)
 * คือสิ่งที่ทำให้ยิม Trainer และผู้เข้าร่วมจองซ้อนกันไม่ได้จริง ๆ
 */
class SlotLock extends Model
{
    public const GYM = 'gym';
    public const TRAINER = 'trainer';
    public const MEMBER = 'member';

    public $timestamps = false;

    protected $fillable = ['reservation_id', 'resource', 'resource_id', 'slot_start'];

    protected function casts(): array
    {
        return ['slot_start' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
