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

    public const METHOD_TRUEMONEY = 'truemoney';

    public const METHOD_RABBIT_LINEPAY = 'rabbit_linepay';

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
        $label = match (true) {
            $this->provider === self::PROVIDER_MANUAL => 'แอดมินบันทึก',
            $this->method === self::METHOD_CARD => 'บัตร',
            $this->method !== null => \App\Services\Payments\Omise\PaymentMethods::label($this->method),
            default => 'Omise',
        };

        $card = $this->payload['card'] ?? null;

        return $card ? $label.' '.($card['brand'] ?? '').' ••'.($card['last_digits'] ?? '') : $label;
    }

    /** Omise คืนเงินอัตโนมัติได้เฉพาะบางช่องทาง (บัตร TrueMoney Rabbit LINE Pay) พร้อมเพย์และ Mobile Banking ต้องโอนคืนเอง */
    public function refundableOnline(): bool
    {
        return $this->provider === self::PROVIDER_OMISE && \App\Services\Payments\Omise\PaymentMethods::refundable($this->method);
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_OMISE => 'Omise',
            default => 'บันทึกโดยแอดมิน',
        };
    }
}
