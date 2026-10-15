<?php

namespace App\Support;

/** ราคาที่คิดให้การจองหนึ่งรายการ เก็บเป็น JSON ไว้กับการจองเพื่อให้ย้อนดูได้ว่าคิดจากอะไร */
final class PriceQuote
{
    public function __construct(
        public readonly string $mode,
        public readonly int $people,
        public readonly int $hours,
        public readonly float $perHour,
        public readonly float $amount,
    ) {}

    public static function fromArray(?array $raw): ?self
    {
        if (! $raw || ! isset($raw['per_hour'])) {
            return null;
        }

        return new self(
            (string) ($raw['mode'] ?? PriceTable::WITH_TRAINER),
            (int) ($raw['people'] ?? 1),
            (int) ($raw['hours'] ?? 1),
            (float) $raw['per_hour'],
            (float) ($raw['amount'] ?? 0),
        );
    }

    public function perPersonPerHour(): float
    {
        return round($this->perHour / $this->people, 2);
    }

    public function withTrainer(): bool
    {
        return $this->mode === PriceTable::WITH_TRAINER;
    }

    /** เช่น "3 คน × ฿450/คน/ชม. × 2 ชม." */
    public function label(): string
    {
        $perPerson = '฿'.number_format($this->perPersonPerHour(), 0).'/คน/ชม.';
        $hours = $this->hours.' ชม.';

        return $this->people.' คน × '.$perPerson.' × '.$hours.($this->withTrainer() ? ' (มี Trainer)' : ' (ไม่มี Trainer)');
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'people' => $this->people,
            'hours' => $this->hours,
            'per_hour' => $this->perHour,
            'per_person_per_hour' => $this->perPersonPerHour(),
            'amount' => $this->amount,
        ];
    }
}
