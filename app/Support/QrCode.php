<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * สร้าง QR เป็น SVG ในเครื่อง
 *
 * ตั้งใจไม่พึ่งบริการสร้าง QR ภายนอก เพราะลิงก์คำเชิญเข้าทีมไม่ควรถูกส่งออกไป
 * ให้บุคคลที่สาม และหน้าเว็บไม่ควรพังเมื่อบริการนั้นล่มหรือถูกบล็อก
 */
class QrCode
{
    public static function svg(string $text, int $size = 240, string $dark = '#1c2f3a', string $light = '#ffffff'): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();

        $width = $matrix->getWidth();
        $height = $matrix->getHeight();
        $quiet = 2;
        $total = $width + $quiet * 2;

        $paths = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    // รวมโมดูลเป็น path เดียว ได้ไฟล์เล็กกว่าการสร้าง rect ทีละตัว
                    $paths[] = 'M'.($x + $quiet).','.($y + $quiet).'h1v1h-1z';
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img">'
            .'<rect width="%1$d" height="%1$d" fill="%3$s"/>'
            .'<path d="%4$s" fill="%5$s"/>'
            .'</svg>',
            $total,
            $size,
            $light,
            implode('', $paths),
            $dark,
        );
    }

    /** ใช้ฝังใน src ของ img ได้โดยตรง */
    public static function dataUri(string $text, int $size = 240): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $size));
    }
}
