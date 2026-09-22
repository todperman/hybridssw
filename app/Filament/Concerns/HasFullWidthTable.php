<?php

namespace App\Filament\Concerns;

use Filament\Support\Enums\Width;

/**
 * ให้หน้าตารางใช้ความกว้างเต็มจอ
 *
 * ใส่เฉพาะหน้าที่เป็นตาราง ไม่ใส่หน้าฟอร์ม เพราะฟอร์มที่กว้างเต็มจอ
 * จะได้บรรทัดยาวเกินไปจนอ่านยาก และช่องกรอกจะยืดจนดูไม่เป็นระเบียบ
 */
trait HasFullWidthTable
{
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
