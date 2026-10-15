<?php

namespace App\Services\Payments\Omise;

/**
 * รายการช่องทางชำระผ่าน Omise ที่ระบบนี้รองรับ
 *
 * ที่เดียวที่รู้ว่าแต่ละช่องทางชื่ออะไร โลโก้ไหน ส่ง source type อะไรให้ Omise
 * และต้องเปิดอะไรในบัญชี Omise (capability) ถึงจะใช้ได้
 *
 * kind:
 *   qr       = ได้ QR มาสแกน (พร้อมเพย์)
 *   card     = ฟอร์มบัตรของ Omise.js
 *   redirect = พาไปหน้าของธนาคาร/วอลเล็ต แล้วกลับมาที่ return_uri
 */
final class PaymentMethods
{
    public const PROMPTPAY = 'promptpay';

    public const CARD = 'card';

    public const MOBILE_BANKING = 'mobile_banking';

    public const RABBIT_LINEPAY = 'rabbit_linepay';

    public const TRUEMONEY = 'truemoney';

    /** ธนาคารที่ Omise รองรับ Mobile Banking ในไทย เรียงตามความนิยม */
    public const BANKS = [
        'mobile_banking_kbank' => ['label' => 'K PLUS', 'bank' => 'กสิกรไทย', 'logo' => 'kplus.svg'],
        'mobile_banking_scb' => ['label' => 'SCB EASY', 'bank' => 'ไทยพาณิชย์', 'logo' => 'scb.svg'],
        'mobile_banking_bay' => ['label' => 'KMA', 'bank' => 'กรุงศรี', 'logo' => 'bay.svg'],
        'mobile_banking_bbl' => ['label' => 'Bualuang mBanking', 'bank' => 'กรุงเทพ', 'logo' => 'bbl.svg'],
        'mobile_banking_ktb' => ['label' => 'Krungthai NEXT', 'bank' => 'กรุงไทย', 'logo' => 'ktb.svg'],
    ];

    /** @return array<string, array{label: string, hint: string, kind: string, logos: array<int, string>, refundable: bool}> */
    public static function all(): array
    {
        return [
            self::PROMPTPAY => [
                'label' => 'พร้อมเพย์',
                'hint' => 'สแกน QR ด้วยแอปธนาคารใดก็ได้',
                'kind' => 'qr',
                'logos' => ['promptpay.svg'],
                'refundable' => false,
            ],
            self::CARD => [
                'label' => 'บัตรเครดิต/เดบิต',
                'hint' => 'Visa · Mastercard · JCB',
                'kind' => 'card',
                'logos' => ['visa.svg', 'mastercard.svg', 'jcb.svg'],
                'refundable' => true,
            ],
            self::MOBILE_BANKING => [
                'label' => 'Mobile Banking',
                'hint' => 'เปิดแอปธนาคารแล้วกดยืนยัน',
                'kind' => 'redirect',
                'logos' => ['kplus.svg', 'scb.svg', 'bay.svg', 'bbl.svg', 'ktb.svg'],
                'refundable' => false,
            ],
            self::RABBIT_LINEPAY => [
                'label' => 'Rabbit LINE Pay',
                'hint' => 'ยืนยันในแอป LINE',
                'kind' => 'redirect',
                'logos' => ['rabbit-linepay.svg'],
                'refundable' => true,
            ],
            self::TRUEMONEY => [
                'label' => 'TrueMoney Wallet',
                'hint' => 'ใส่เบอร์แล้วยืนยันด้วย OTP',
                'kind' => 'redirect',
                'logos' => ['truemoney.svg'],
                'refundable' => true,
            ],
        ];
    }

    public static function get(string $method): ?array
    {
        return self::all()[$method] ?? null;
    }

    public static function label(string $method): string
    {
        if (isset(self::BANKS[$method])) {
            return self::BANKS[$method]['label'].' ('.self::BANKS[$method]['bank'].')';
        }

        return self::get($method)['label'] ?? $method;
    }

    public static function logoUrl(string $file): string
    {
        return asset('img/payments/'.$file);
    }

    /** Omise คืนเงินอัตโนมัติได้ไหมสำหรับช่องทางนี้ (รวมช่องทางย่อยของธนาคาร) */
    public static function refundable(?string $method): bool
    {
        if ($method === null) {
            return false;
        }

        if (isset(self::BANKS[$method])) {
            return false;
        }

        return self::get($method)['refundable'] ?? false;
    }

    /**
     * ช่องทางที่ใช้ได้จริง = ที่ตั้งไว้ใน config และบัญชี Omise เปิดแล้ว (จาก capability)
     * capability ว่างแปลว่าถามไม่ได้ ใช้ตาม config ไปก่อน
     *
     * @param  array<int, string>  $configured
     * @param  array<int, string>|null  $enabled  ชื่อ payment method จาก capability ของ Omise
     * @return array<string, array<int, string>> method => source types ที่ใช้ได้ (mobile banking = รายธนาคาร)
     */
    public static function available(array $configured, ?array $enabled): array
    {
        $out = [];

        foreach ($configured as $method) {
            if (! isset(self::all()[$method])) {
                continue;
            }

            if ($method === self::MOBILE_BANKING) {
                $banks = $enabled === null
                    ? array_keys(self::BANKS)
                    : array_values(array_intersect(array_keys(self::BANKS), $enabled));

                if ($banks !== []) {
                    $out[$method] = $banks;
                }

                continue;
            }

            if ($enabled === null || in_array($method, $enabled, true)) {
                $out[$method] = [$method];
            }
        }

        return $out;
    }
}
