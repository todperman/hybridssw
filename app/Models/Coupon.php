<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Support\PriceQuote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * คูปองส่วนลด
 *
 * ส่วนลดหักจากยอดที่ต้องชำระจริง (หลังหักส่วนของคนที่ใช้ฟรีแล้ว) และไม่ติดลบ
 * ยอดหลังหักถ้าเหลือ 0 การจองยืนยันทันที ถ้าเหลือไม่ถึง 20 บาทใช้ไม่ได้ (ขั้นต่ำของช่องทางชำระ)
 */
class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const APPLIES_BOTH = 'both';

    public const APPLIES_WITH_TRAINER = 'with_trainer';

    public const APPLIES_WITHOUT_TRAINER = 'without_trainer';

    /** ยอดต่ำสุดที่ช่องทางชำระออนไลน์รับได้ (บาท) */
    public const MIN_PAYABLE = 20;

    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'max_discount', 'min_amount', 'applies_to',
        'branch_id', 'starts_at', 'expires_at', 'max_uses', 'max_uses_per_member', 'is_active', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_member' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $c) => $c->code = static::normalize($c->code));
    }

    /** โค้ดเทียบแบบไม่สนตัวพิมพ์และช่องว่าง */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = static::normalize($code);

        return $code === '' ? null : static::where('code', $code)->first();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** จำนวนครั้งที่ใช้แล้ว นับเฉพาะการจองที่ยังถือเวลาอยู่ */
    public function usedCount(?int $payerMemberId = null): int
    {
        return $this->reservations()
            ->whereIn('status', ReservationStatus::holding())
            ->when($payerMemberId, fn ($q) => $q->where('payer_member_id', $payerMemberId))
            ->count();
    }

    public function usesLeft(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - $this->usedCount());
    }

    public function isLive(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function discountLabel(): string
    {
        if ($this->type === self::TYPE_PERCENT) {
            $label = rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'%';

            return $this->max_discount ? $label.' (สูงสุด ฿'.number_format((float) $this->max_discount, 0).')' : $label;
        }

        return '฿'.number_format((float) $this->value, 0);
    }

    public function appliesToLabel(): string
    {
        return match ($this->applies_to) {
            self::APPLIES_WITH_TRAINER => 'เฉพาะมี Trainer',
            self::APPLIES_WITHOUT_TRAINER => 'เฉพาะไม่มี Trainer',
            default => 'ทุกแบบ',
        };
    }

    /** ส่วนลดที่จะได้จากยอดนี้ ยังไม่ตรวจเงื่อนไข */
    public function discountFor(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $discount = $this->type === self::TYPE_PERCENT
            ? $amount * ((float) $this->value) / 100
            : (float) $this->value;

        if ($this->type === self::TYPE_PERCENT && $this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $amount), 2);
    }

    /**
     * ตรวจทุกเงื่อนไขกับการจองที่กำลังจะเกิด แล้วคืนราคาที่หักส่วนลดแล้ว
     * ข้อความในข้อยกเว้นแสดงให้ลูกค้าเห็นตรง ๆ
     */
    public function apply(PriceQuote $quote, Branch $branch, Member $payer): PriceQuote
    {
        if (! $this->isLive()) {
            throw new ReservationException('โค้ดนี้หมดอายุหรือยังไม่เปิดใช้', 'coupon_inactive');
        }

        if ($this->branch_id !== null && $this->branch_id !== $branch->id) {
            throw new ReservationException('โค้ดนี้ใช้ได้เฉพาะสาขา '.$this->branch?->name, 'coupon_branch');
        }

        if ($this->applies_to === self::APPLIES_WITH_TRAINER && ! $quote->withTrainer()) {
            throw new ReservationException('โค้ดนี้ใช้ได้เฉพาะการจองที่มี Trainer', 'coupon_mode');
        }

        if ($this->applies_to === self::APPLIES_WITHOUT_TRAINER && $quote->withTrainer()) {
            throw new ReservationException('โค้ดนี้ใช้ได้เฉพาะการจองที่ไม่มี Trainer', 'coupon_mode');
        }

        if ($quote->amount <= 0) {
            throw new ReservationException('การจองนี้ไม่มียอดให้ลดแล้ว', 'coupon_nothing');
        }

        if ($quote->amount < (float) $this->min_amount) {
            throw new ReservationException('โค้ดนี้ใช้กับยอดตั้งแต่ ฿'.number_format((float) $this->min_amount, 0).' ขึ้นไป', 'coupon_min');
        }

        if ($this->max_uses !== null && $this->usedCount() >= $this->max_uses) {
            throw new ReservationException('โค้ดนี้ถูกใช้ครบจำนวนแล้ว', 'coupon_exhausted');
        }

        if ($this->max_uses_per_member !== null && $this->usedCount($payer->id) >= $this->max_uses_per_member) {
            throw new ReservationException('คุณใช้โค้ดนี้ครบจำนวนที่กำหนดแล้ว', 'coupon_member_limit');
        }

        $discount = $this->discountFor($quote->amount);
        $remaining = round($quote->amount - $discount, 2);

        if ($remaining > 0 && $remaining < self::MIN_PAYABLE) {
            throw new ReservationException('ใช้โค้ดนี้แล้วยอดจะเหลือต่ำกว่า ฿'.self::MIN_PAYABLE.' ซึ่งช่องทางชำระรับไม่ได้', 'coupon_too_small');
        }

        return $quote->withDiscount($this->code, $discount);
    }

    public function scopeLive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
