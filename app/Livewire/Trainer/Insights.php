<?php

namespace App\Livewire\Trainer;

use App\Models\Trainer;
use App\Services\BookingHeatmapService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * สถิติการจองของเทรนเนอร์
 *
 * มีสองมุมมอง
 * - ของทีมฉัน: ทีมเรามักเล่นชั่วโมงไหน ใช้วางตารางตัวเอง
 * - ทั้งสาขา: ชั่วโมงไหนเต็มเร็ว จะได้รู้ว่าต้องจองล่วงหน้าแค่ไหน
 *
 * มุมมองทั้งสาขาเป็นตัวเลขอัตราการใช้ที่นั่งล้วนๆ ไม่มีชื่อใครปรากฏ
 * จึงไม่ขัดกับหลักที่ว่าเทรนเนอร์ไม่ควรเห็นว่าที่นั่งของคนอื่นเป็นของใคร
 */
class Insights extends Component
{
    public string $scope = 'mine';

    public int $weeks = 8;

    public const WEEK_OPTIONS = [4 => '4 สัปดาห์', 8 => '8 สัปดาห์', 12 => '12 สัปดาห์'];

    public const SCOPES = ['mine' => 'ทีมของฉัน', 'branch' => 'ทั้งสาขา'];

    public function trainer(): Trainer
    {
        return auth()->user()->trainer->loadMissing('branch');
    }

    public function setScope(string $scope): void
    {
        $this->scope = array_key_exists($scope, self::SCOPES) ? $scope : 'mine';
        $this->refreshData();
    }

    public function updatedWeeks(): void
    {
        $this->refreshData();
    }

    #[Computed]
    public function grid(): array
    {
        $trainer = $this->trainer();
        $to = CarbonImmutable::now();

        return app(BookingHeatmapService::class)->grid(
            $trainer->branch_id,
            $to->subWeeks($this->weeks)->startOfDay(),
            $to,
            $this->scope === 'mine' ? $trainer->id : null,
        );
    }

    #[Computed]
    public function insights(): array
    {
        return app(BookingHeatmapService::class)->insights($this->grid);
    }

    public function levelOf(float $rate): int
    {
        return app(BookingHeatmapService::class)->level($rate);
    }

    protected function refreshData(): void
    {
        unset($this->grid, $this->insights);
    }

    public function render()
    {
        return view('livewire.trainer.insights')->layout('layouts.app');
    }
}
