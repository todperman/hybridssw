<?php

namespace App\Support;

use App\Models\Branch;

/**
 * ตารางราคาต่อชั่วโมงตามจำนวนผู้เข้าร่วม แยกแบบมี Trainer กับไม่มี Trainer
 *
 * แต่ละแบบเก็บ "ราคารวมของทั้งกลุ่มต่อชั่วโมง" เรียงตามจำนวนคน 1, 2, 3, ...
 * เกินจำนวนที่ตั้งไว้ คิดเพิ่มต่อคนต่อชั่วโมงตาม extra_per_person
 *
 *   with_trainer:    tiers [1050, 1550, 1950, 2350, 2650], extra 530 → 7 คน = 2650 + 2 × 530
 *   without_trainer: tiers [500, 950, 1350, 1700, 2000],   extra 400 → ทุกคนจ่าย 500/475/450/425/400
 *
 * ตั้งรายสาขาในหลังบ้าน ไม่ตั้งใช้ค่าตั้งต้นจาก config/gym.php
 */
final class PriceTable
{
    public const WITH_TRAINER = 'with_trainer';

    public const WITHOUT_TRAINER = 'without_trainer';

    /** @param  array<string, array{tiers: array<int, float|int>, extra_per_person: float|int}>  $table */
    public function __construct(public readonly array $table) {}

    public static function for(Branch $branch): self
    {
        return self::fromArray($branch->pricing ?: config('gym.pricing', []));
    }

    public static function fromArray(?array $raw): self
    {
        $table = [];

        foreach ([self::WITH_TRAINER, self::WITHOUT_TRAINER] as $mode) {
            $tiers = array_values(array_map(fn ($v) => round((float) $v, 2), (array) ($raw[$mode]['tiers'] ?? [])));
            $table[$mode] = [
                'tiers' => $tiers,
                'extra_per_person' => round((float) ($raw[$mode]['extra_per_person'] ?? 0), 2),
            ];
        }

        return new self($table);
    }

    /** ตั้งราคาเดียวทุกกลุ่มทุกขนาด (แบบเดิม: ต่อชั่วโมงต่อการจอง) */
    public static function flat(float $perHour): self
    {
        return self::fromArray([
            self::WITH_TRAINER => ['tiers' => [$perHour], 'extra_per_person' => 0],
            self::WITHOUT_TRAINER => ['tiers' => [$perHour], 'extra_per_person' => 0],
        ]);
    }

    /** ตั้งราคาแล้วทั้งสองแบบ (ราคาคนเดียวต้องมากกว่าศูนย์) */
    public function isSet(): bool
    {
        foreach ($this->table as $mode) {
            if (($mode['tiers'][0] ?? 0) <= 0) {
                return false;
            }
        }

        return true;
    }

    /** ราคารวมของกลุ่มต่อชั่วโมง */
    public function perHour(int $people, bool $withTrainer): float
    {
        $mode = $this->table[$withTrainer ? self::WITH_TRAINER : self::WITHOUT_TRAINER];
        $tiers = $mode['tiers'];
        $people = max(1, $people);

        if ($tiers === []) {
            return 0.0;
        }

        if ($people <= count($tiers)) {
            return $tiers[$people - 1];
        }

        return round(end($tiers) + ($people - count($tiers)) * $mode['extra_per_person'], 2);
    }

    /** ราคาถูกที่สุดต่อชั่วโมงที่เป็นไปได้ ใช้ตรวจขั้นต่ำของช่องทางชำระ */
    public function minPerHour(): float
    {
        $all = [];

        foreach ($this->table as $mode) {
            $all = array_merge($all, $mode['tiers']);
        }

        return $all === [] ? 0.0 : (float) min($all);
    }

    /**
     * ราคาสำหรับการจองหนึ่งรายการ พร้อมรายละเอียดเก็บไว้กับการจอง
     *
     * @param  array<int, int>  $freeHours  ชั่วโมงฟรีของแต่ละคน (member_id => ชม.) จากสิทธิ์ประเภทสมาชิก
     *                                      ส่วนของคนนั้นคือ ราคารวม ÷ จำนวนคน จึงหักออกเท่านั้น ไม่กระทบคนอื่น
     */
    public function quote(int $people, bool $withTrainer, int $hours, array $freeHours = []): PriceQuote
    {
        $people = max(1, $people);
        $hours = max(1, $hours);
        $perHour = $this->perHour($people, $withTrainer);
        $perPerson = $perHour / $people;

        $free = array_filter(array_map(fn ($h) => min((int) $h, $hours), $freeHours), fn ($h) => $h > 0);
        $discount = $perPerson * array_sum($free);

        return new PriceQuote(
            mode: $withTrainer ? self::WITH_TRAINER : self::WITHOUT_TRAINER,
            people: $people,
            hours: $hours,
            perHour: $perHour,
            amount: round(max(0, $perHour * $hours - $discount), 2),
            free: $free,
        );
    }

    public function toArray(): array
    {
        return $this->table;
    }
}
