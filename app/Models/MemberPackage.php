<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPackage extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'member_id',
        'package_id',
        'package_name',
        'credits_total',
        'credits_used',
        'price_paid',
        'starts_at',
        'expires_at',
        'status',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'credits_total' => 'integer',
            'credits_used' => 'integer',
            'price_paid' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creditsRemaining(): int
    {
        return max(0, $this->credits_total - $this->credits_used);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->endOfDay()->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ! $this->isExpired()
            && $this->creditsRemaining() > 0;
    }

    /** แพ็กเกจที่ยังใช้ได้: ยัง active, ยังไม่หมดอายุ */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('expires_at', '>=', now()->toDateString());
    }
}
