<?php

namespace App\Filament\Resources\MemberGroups\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\MemberGroups\MemberGroupResource;
use Filament\Resources\Pages\ListRecords;

class ListMemberGroups extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = MemberGroupResource::class;

    /** เทรนเนอร์สร้างกลุ่มเองที่หน้าบ้าน หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
