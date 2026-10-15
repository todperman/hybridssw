<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ประเภทสมาชิกที่ได้สิทธิ์ใช้ฟรี เช่น บุคลากร รพ. และแพทย์
 * สมาชิกทั่วไปไม่มีประเภท ไม่ต้องมีแถวในตารางนี้
 */
class MemberType extends Model
{
    protected $fillable = ['code', 'name', 'description', 'free_hours_per_month', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'free_hours_per_month' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function isUnlimited(): bool
    {
        return $this->free_hours_per_month === null;
    }

    public function quotaLabel(): string
    {
        return $this->isUnlimited() ? 'ฟรีไม่จำกัด' : 'ฟรี '.$this->free_hours_per_month.' ชม./เดือน';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** @return array<int, string> ตัวเลือกสำหรับฟอร์มแอดมิน */
    public static function options(): array
    {
        return static::active()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }
}
