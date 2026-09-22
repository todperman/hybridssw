<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * กลุ่มลูกทีมที่เทรนเนอร์สร้างเอง เช่น "กลุ่มเช้า จ-พ-ศ"
 *
 * เทรนเนอร์เป็นคนสร้างและจัดสมาชิก แต่ "รับได้สูงสุดกี่คน" เป็นของแอดมิน
 * ค่าเริ่มต้นมาจาก branches.max_group_size และแอดมินตั้งทับรายกลุ่มได้ที่ max_members
 */
class MemberGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'branch_id',
        'name',
        'description',
        'color',
        'max_members',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_members' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_group_members')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /** ชุดสีประจำกลุ่ม คืนค่าเสมอถึงคีย์ในฐานข้อมูลจะเป็นค่าเก่าที่ถูกถอดออกแล้ว */
    public function palette(): array
    {
        return \App\Support\GroupPalette::get($this->color);
    }

    /** เพดานจริงของกลุ่มนี้ ใช้ค่าเฉพาะกลุ่มก่อน ไม่มีจึงถอยไปใช้ค่าของสาขา */
    public function capacity(): int
    {
        return $this->max_members ?? $this->branch->max_group_size;
    }

    public function seatsRemaining(): int
    {
        return max(0, $this->capacity() - $this->members()->count());
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= $this->capacity();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
