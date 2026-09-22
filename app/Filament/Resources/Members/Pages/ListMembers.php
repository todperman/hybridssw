<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = MemberResource::class;

    /** ลูกทีมเข้าระบบผ่านลิงก์ชวนของเทรนเนอร์ หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
