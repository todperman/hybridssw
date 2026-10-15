<?php

namespace App\Filament\Resources\MemberTypes\Pages;

use App\Filament\Resources\MemberTypes\MemberTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListMemberTypes extends ListRecords
{
    protected static string $resource = MemberTypeResource::class;

    /** ปุ่มสร้างอยู่ที่หัวตารางแล้ว (เปิดเป็นแผงข้าง) ไม่ต้องมีซ้ำที่หัวหน้า */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
