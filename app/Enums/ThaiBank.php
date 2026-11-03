<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** ธนาคารในไทยที่ใช้รับโอนส่วนแบ่ง รหัสตามธนาคารแห่งประเทศไทย (ตัวย่อสากล) */
enum ThaiBank: string implements HasLabel
{
    case KBANK = 'KBANK';
    case SCB = 'SCB';
    case BBL = 'BBL';
    case KTB = 'KTB';
    case BAY = 'BAY';
    case TTB = 'TTB';
    case GSB = 'GSB';
    case BAAC = 'BAAC';
    case KKP = 'KKP';
    case CIMB = 'CIMB';
    case UOB = 'UOB';
    case TISCO = 'TISCO';
    case LHB = 'LHB';
    case GHB = 'GHB';
    case ICBC = 'ICBC';

    public function label(): string
    {
        return match ($this) {
            self::KBANK => 'กสิกรไทย',
            self::SCB => 'ไทยพาณิชย์',
            self::BBL => 'กรุงเทพ',
            self::KTB => 'กรุงไทย',
            self::BAY => 'กรุงศรีอยุธยา',
            self::TTB => 'ทีเอ็มบีธนชาต (ttb)',
            self::GSB => 'ออมสิน',
            self::BAAC => 'ธ.ก.ส.',
            self::KKP => 'เกียรตินาคินภัทร',
            self::CIMB => 'ซีไอเอ็มบี ไทย',
            self::UOB => 'ยูโอบี',
            self::TISCO => 'ทิสโก้',
            self::LHB => 'แลนด์ แอนด์ เฮ้าส์',
            self::GHB => 'ธอส.',
            self::ICBC => 'ไอซีบีซี (ไทย)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /** สีประจำธนาคาร ไว้ทำป้าย */
    public function color(): string
    {
        return match ($this) {
            self::KBANK => '#138f2d',
            self::SCB => '#4e2e7f',
            self::BBL => '#1e4598',
            self::KTB => '#1ba5e1',
            self::BAY => '#fec43b',
            self::TTB => '#0050f0',
            self::GSB => '#eb198d',
            self::BAAC => '#4b9b1d',
            self::KKP => '#635f98',
            self::CIMB => '#7e2f36',
            self::UOB => '#0b3979',
            self::TISCO => '#12549f',
            self::LHB => '#6d6e71',
            self::GHB => '#f57d23',
            self::ICBC => '#c50f1f',
        };
    }

    /** จำนวนหลักของเลขบัญชีที่ธนาคารนี้ใช้ */
    public function accountDigits(): array
    {
        return match ($this) {
            self::GSB => [12, 15],
            self::BAAC => [10, 12],
            default => [10, 10],
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $b) => [$b->value => $b->label()])->all();
    }

    /** 1234567890 → 123-4-56789-0 รูปแบบที่คนไทยคุ้น */
    public static function formatAccount(?string $digits): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $digits);

        if ($d === '') {
            return null;
        }

        return strlen($d) === 10 ? substr($d, 0, 3).'-'.substr($d, 3, 1).'-'.substr($d, 4, 5).'-'.substr($d, 9, 1) : $d;
    }
}
