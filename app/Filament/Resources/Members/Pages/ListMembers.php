<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = MemberResource::class;

    /** สมาชิกสมัครเองที่หน้าบ้านหรือเข้าผ่านลิงก์ชวน หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            // Filament ส่งค่าเข้า closure ตามชื่อพารามิเตอร์ ต้องชื่อ $query เท่านั้น
            'pending' => Tab::make('รออนุมัติ')
                ->badge(fn () => MemberResource::pendingCount() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', MemberStatus::Pending->value)),

            'all' => Tab::make('ทั้งหมด'),
        ];
    }

    /** มีคนรออนุมัติก็เปิดแท็บนั้นก่อน แอดมินจะเห็นงานที่ต้องทำทันที */
    public function getDefaultActiveTab(): string|int|null
    {
        return MemberResource::pendingCount() > 0 ? 'pending' : 'all';
    }
}
