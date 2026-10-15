<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Trainer;
use App\Services\Reservations\Availability;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_demo_seed_gives_a_branch_you_can_book_straight_away(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00')); // วันพุธ

        $this->seed(DatabaseSeeder::class);

        $main = Branch::where('code', 'SSW-MAIN')->sole();
        $this->assertTrue($main->priceTable()->isSet());

        $trainers = app(Availability::class)->availableTrainers($main, CarbonImmutable::parse('2026-10-07 18:00'), 1);
        $this->assertCount(2, $trainers);

        $this->artisan('gym:doctor')->assertSuccessful();
    }

    #[Test]
    public function the_production_seed_creates_one_branch_and_admin_without_a_price(): void
    {
        putenv('ADMIN_EMAIL=owner@example.test');
        putenv('ADMIN_PASSWORD=a-long-test-password');

        try {
            $this->seed(ProductionSeeder::class);
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $this->assertSame(1, Branch::count());
        // ยังไม่ตั้งราคาเอง จึงใช้ตารางตั้งต้นจาก config ซึ่งใช้งานได้ทันที
        $this->assertNull(Branch::sole()->pricing);
        $this->assertSame(1050.0, Branch::sole()->priceTable()->perHour(1, true));
        $this->assertSame(0, Trainer::count());
    }
}
