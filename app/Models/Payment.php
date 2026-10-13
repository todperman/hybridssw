<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PROVIDER_MANUAL = 'manual';
    public const PROVIDER_OMISE = 'omise';

    public const METHOD_PROMPTPAY = 'promptpay';
    public const METHOD_CARD = 'card';
    public const METHOD_TRANSFER = 'transfer';

    protected $fillable = [
        'reservation_id', 'payer_member_id', 'provider', 'method', 'provider_charge_id',
        'amount', 'currency', 'status', 'failure_message', 'paid_at',
        'needs_review', 'recorded_by_user_id', 'note', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'needs_review' => 'boolean',
            'payload' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payer_member_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function methodLabel(): string
    {
        $label = match ($this->method) {
            self::METHOD_PROMPTPAY => 'พร้อมเพย์',
            self::METHOD_CARD => 'บัตร',
            default => $this->provider === self::PROVIDER_MANUAL ? 'แอดมินบันทึก' : 'Omise',
        };

        $card = $this->payload['card'] ?? null;

        return $card ? $label.' '.($card['brand'] ?? '').' ••'.($card['last_digits'] ?? '') : $label;
    }

    /** Omise คืนเงินให้อัตโนมัติได้เฉพาะบัตร พร้อมเพย์ต้องโอนคืนเอง */
    public function refundableOnline(): bool
    {
        return $this->provider === self::PROVIDER_OMISE && $this->method === self::METHOD_CARD;
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_OMISE => 'Omise',
            default => 'บันทึกโดยแอดมิน',
        };
    }
}
