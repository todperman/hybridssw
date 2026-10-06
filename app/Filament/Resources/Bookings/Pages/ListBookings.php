<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Concerns\HasFullWidthTable;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    use HasFullWidthTable;

    protected static string $resource = BookingResource::class;

    /** การจองเกิดที่หน้าบ้าน หลังบ้านจึงไม่มีปุ่มสร้าง */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            // ส่งเป็น closure ให้นับใหม่ทุกครั้งที่ตารางรีเฟรช
            // ใส่เป็นตัวเลขตรง ๆ จะค้างค่าเดิมหลังกดอนุมัติ จนกว่าจะโหลดหน้าใหม่
            'pending' => Tab::make('รออนุมัติ')
                ->badge(fn () => static::pendingCount() ?: null)
                ->badgeColor('warning')
                // Filament ส่งค่าเข้า closure ตามชื่อพารามิเตอร์ ต้องชื่อ $query เท่านั้น
                // ตั้งชื่ออื่นแล้วจะได้ query เปล่าที่ไม่มี model หน้าจะพังตอนกรอง
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatus::Pending->value)),

            'all' => Tab::make('ทั้งหมด'),
        ];
    }

    /** มีคำขอค้างอยู่ก็เปิดแท็บนั้นก่อน แอดมินจะเห็นงานที่ต้องทำทันทีที่เข้ามา */
    public function getDefaultActiveTab(): string|int|null
    {
        return static::pendingCount() > 0 ? 'pending' : 'all';
    }

    protected static function pendingCount(): int
    {
        return Booking::where('status', BookingStatus::Pending->value)->count();
    }
}
