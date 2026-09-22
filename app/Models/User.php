<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'first_name',
        'last_name',
        'nickname',
        'email',
        'password',
        'role',
        'phone',
        'avatar_path',
        'line_user_id',
        'is_active',
        'last_login_at',
    ];

    /**
     * ค่าเริ่มต้นระดับโมเดล ไม่ใช่แค่ระดับ DB
     * ถ้าพึ่ง default ของคอลัมน์อย่างเดียว อ็อบเจ็กต์ที่เพิ่งสร้างจะมี role เป็น null
     * จนกว่าจะอ่านกลับจากฐานข้อมูล ซึ่งทำให้โค้ดที่เช็คสิทธิ์ทันทีหลังสมัครพัง
     */
    protected $attributes = [
        'role' => 'member',
        'is_active' => true,
    ];

    /**
     * name เป็นคอลัมน์ที่ฐานข้อมูลคำนวณจาก first_name + last_name ให้เอง
     * อ่านได้แต่เขียนไม่ได้ ถ้าพยายามเขียนจะโดน MySQL ปฏิเสธ
     */
    protected $guarded = ['name'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function trainer(): HasOne
    {
        return $this->hasOne(Trainer::class);
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * URL รูปโปรไฟล์ ไม่มีรูปคืน null แล้วให้ฝั่งวิวถอยไปใช้ตัวอักษรย่อแทน
     *
     * ตั้งใจคืน path แบบสัมพัทธ์ ไม่ผ่าน Storage::url() ซึ่งเติม APP_URL ไว้ข้างหน้า
     * เพราะถ้า APP_URL ตั้งไม่ตรงกับที่เปิดใช้จริง (เช่นคนละพอร์ต) รูปจะแตกทั้งระบบ
     * ส่วน <img src> ไม่จำเป็นต้องเป็น URL เต็มอยู่แล้ว
     */
    /**
     * ชื่อที่ใช้เรียกหน้างาน ชื่อเล่นมาก่อนเพราะสั้นและจำง่ายกว่าชื่อจริง
     * ถ้ายังไม่กรอกก็ถอยไปใช้ชื่อจริงตามเดิม
     */
    public function displayName(): string
    {
        // users.name เป็น generated column ค่าจะยังไม่มีจนกว่าจะอ่านกลับจากฐานข้อมูล
        // จึงต้องประกอบจากชื่อ-นามสกุลเองเป็นทางสำรอง ไม่งั้นคืน null แล้ว type error
        return $this->nickname
            ?: ($this->name ?: trim(($this->first_name ?? '').' '.($this->last_name ?? '')));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? '/storage/'.ltrim($this->avatar_path, '/')
            : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role->canAccessAdminPanel();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTrainer(): bool
    {
        return $this->role === UserRole::Trainer;
    }

    public function isMember(): bool
    {
        return $this->role === UserRole::Member;
    }

    /** แอดมินเห็นทุกสาขา บทบาทอื่นเห็นเฉพาะสาขาตัวเอง */
    public function canSeeAllBranches(): bool
    {
        return $this->role->isGlobal();
    }
}
