<?php

namespace App\Support;

use App\Models\Branch;

/**
 * ตารางส่วนแบ่ง: ราคาที่ลูกค้าจ่ายต่อชั่วโมง (แบบมี Trainer) แยกเป็น "ค่า Trainer" กับ "ค่า Facility" ตามจำนวนคน
 * เป็นจำนวนเงินคงที่ ไม่ใช่เปอร์เซ็นต์ กำหนดโดยเจ้าของระบบ 2026-11-03:
 *
 *   คน:       1     2     3     4     5    เกินนั้นต่อคน
 *   Trainer:  700  1000  1200  1400  1500   +330
 *   Facility: 350   550   750   950  1150   +200
 *
 * การจองแบบไม่มี Trainer ส่วนแบ่ง = 0 ทั้งหมดเป็น Facility
 * เทรนเนอร์ภายใน (พนักงาน) ได้ 0 (ตัดสินที่ TrainerEarningService ไม่ใช่ที่ตารางนี้)
 */
final class PayoutTable
{
    /** @param  array<int, array{trainer: float, facility: float}>  $tiers  index 0 = 1 คน */
    public function __construct(public readonly array $tiers, public readonly float $extraTrainer, public readonly float $extraFacility) {}

    public static function for(Branch $branch): self
    {
        return self::fromArray($branch->payout ?: config('gym.payout', []));
    }

    public static function fromArray(?array $raw): self
    {
        $tiers = [];

        foreach ((array) ($raw['tiers'] ?? []) as $row) {
            $tiers[] = ['trainer' => round((float) ($row['trainer'] ?? 0), 2), 'facility' => round((float) ($row['facility'] ?? 0), 2)];
        }

        return new self($tiers, round((float) ($raw['extra']['trainer'] ?? 0), 2), round((float) ($raw['extra']['facility'] ?? 0), 2));
    }

    public function isSet(): bool
    {
        return ($this->tiers[0]['trainer'] ?? 0) > 0;
    }

    /** ค่า Trainer ต่อชั่วโมงสำหรับกลุ่มขนาดนี้ */
    public function trainerPerHour(int $people): float
    {
        return $this->split($people)['trainer'];
    }

    public function facilityPerHour(int $people): float
    {
        return $this->split($people)['facility'];
    }

    /** @return array{trainer: float, facility: float} */
    public function split(int $people): array
    {
        $people = max(1, $people);
        $count = count($this->tiers);

        if ($count === 0) {
            return ['trainer' => 0.0, 'facility' => 0.0];
        }

        if ($people <= $count) {
            return $this->tiers[$people - 1];
        }

        $last = $this->tiers[$count - 1];
        $extra = $people - $count;

        return [
            'trainer' => round($last['trainer'] + $extra * $this->extraTrainer, 2),
            'facility' => round($last['facility'] + $extra * $this->extraFacility, 2),
        ];
    }

    /**
     * แถวไหนที่ Trainer + Facility ไม่เท่าราคาที่ลูกค้าจ่าย (แบบมี Trainer) คืนเป็นข้อความไว้เตือนในหลังบ้าน
     *
     * @return array<int, string>
     */
    public function mismatches(PriceTable $prices): array
    {
        $problems = [];

        foreach ($this->tiers as $i => $row) {
            $price = $prices->perHour($i + 1, true);
            $sum = $row['trainer'] + $row['facility'];

            if (abs($sum - $price) > 0.009) {
                $problems[] = ($i + 1).' คน: Trainer '.number_format($row['trainer']).' + Facility '.number_format($row['facility']).' = '.number_format($sum).' แต่ราคาคือ '.number_format($price);
            }
        }

        $extraPrice = $prices->table[PriceTable::WITH_TRAINER]['extra_per_person'] ?? 0;

        if ($this->tiers !== [] && abs(($this->extraTrainer + $this->extraFacility) - $extraPrice) > 0.009) {
            $problems[] = 'คนที่เกินตาราง: '.number_format($this->extraTrainer).' + '.number_format($this->extraFacility).' ไม่เท่าราคาเพิ่มต่อคน '.number_format($extraPrice);
        }

        return $problems;
    }

    /** @return array{tiers: array<int, array{trainer: float, facility: float}>, extra: array{trainer: float, facility: float}} */
    public function toArray(): array
    {
        return ['tiers' => $this->tiers, 'extra' => ['trainer' => $this->extraTrainer, 'facility' => $this->extraFacility]];
    }
}
