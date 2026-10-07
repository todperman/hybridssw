<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PROVIDER_MANUAL = 'manual';
    public const PROVIDER_OMISE = 'omise';

    protected $fillable = [
        'reservation_id', 'payer_member_id', 'provider', 'provider_charge_id',
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

    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_OMISE => 'Omise',
            default => 'บันทึกโดยแอดมิน',
        };
    }
}
