<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasFullWidthTable;
use App\Models\Branch;
use App\Services\BookingHeatmapService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * ความหนาแน่นการจองแยกตามวันในสัปดาห์และชั่วโมง
 *
 * ตั้งใจให้ตอบคำถามที่ตัดสินใจได้จริง ไม่ใช่แค่ดูสวย
 * - ชั่วโมงไหนแน่นจนควรเปิดรอบเพิ่ม (ดูจากคิวสำรองที่ล้น ไม่ใช่แค่เต็ม)
 * - ชั่วโมงไหนว่างเรื้อรังจนควรยุบหรือลดราคาช่วงนอกพีค
 * - ที่นั่งหายไปเท่าไหร่เพราะคนไม่มาตามนัด
 */
class BookingHeatmap extends Page
{
    use HasFullWidthTable;

    protected string $view = 'filament.pages.booking-heatmap';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|\UnitEnum|null $navigationGroup = 'รายงาน';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'ความหนาแน่นการจอง';

    protected static ?string $title = 'ความหนาแน่นการจอง';

    public ?int $branchId = null;

    public int $weeks = 8;

    public const WEEK_OPTIONS = [4 => '4 สัปดาห์', 8 => '8 สัปดาห์', 12 => '12 สัปดาห์', 26 => '6 เดือน'];

    public const DAYS = BookingHeatmapService::DAYS;

    public function mount(): void
    {
        $this->branchId = $this->branches()->keys()->first();
    }

    public function branches()
    {
        $user = auth()->user();

        return Branch::query()
            ->active()
            ->when(! $user->canSeeAllBranches(), fn ($q) => $q->whereKey($user->branch_id))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function range(): array
    {
        $to = CarbonImmutable::now();

        return [$to->subWeeks($this->weeks)->startOfDay(), $to];
    }

    public function grid(): array
    {
        if (! $this->branchId) {
            return ['hours' => [], 'cells' => []];
        }

        [$from, $to] = $this->range();

        return app(BookingHeatmapService::class)->grid($this->branchId, $from, $to);
    }

    public function insights(): array
    {
        return app(BookingHeatmapService::class)->insights($this->grid());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role?->canAccessAdminPanel() ?? false;
    }
}
