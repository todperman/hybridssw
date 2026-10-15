<?php

namespace Tests\Feature\Reservations;

use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Livewire\Reservations\BookingWizard;
use App\Models\Branch;
use App\Models\Reservation;
use App\Support\PriceTable;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/** ตารางราคาตามจำนวนคน แยกมี/ไม่มี Trainer ตามที่เจ้าของระบบกำหนด 2026-10-15 */
class PricingTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00'));
        Notification::fake();
    }

    /** สาขาที่ใช้ตารางราคาจริง (ไม่ตั้งเอง = ค่าตั้งต้นจาก config) */
    protected function realGym(): Branch
    {
        return $this->makeGym(['pricing' => null]);
    }

    #[Test]
    public function with_a_trainer_the_group_pays_the_table_total_and_530_per_extra_person(): void
    {
        $table = PriceTable::for($this->realGym());

        $this->assertSame([1050.0, 1550.0, 1950.0, 2350.0, 2650.0], array_map(fn ($n) => $table->perHour($n, true), range(1, 5)));
        $this->assertSame(2650.0 + 530, $table->perHour(6, true));
        $this->assertSame(2650.0 + 530 * 3, $table->perHour(8, true));
    }

    #[Test]
    public function without_a_trainer_everyone_pays_the_rate_for_the_group_size(): void
    {
        $table = PriceTable::for($this->realGym());

        $perPerson = array_map(fn ($n) => $table->quote($n, false, 1)->perPersonPerHour(), range(1, 7));

        $this->assertSame([500.0, 475.0, 450.0, 425.0, 400.0, 400.0, 400.0], $perPerson);
        $this->assertSame(3 * 450.0, $table->perHour(3, false));
        $this->assertSame(6 * 400.0, $table->perHour(6, false));
    }

    #[Test]
    public function a_booking_stores_what_it_was_priced_on_so_later_table_changes_do_not_touch_it(): void
    {
        $gym = $this->realGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $members = collect(range(1, 3))->map(fn () => $this->makeMember($gym));

        $r = $this->bookAsTrainee($gym, $members[0], [$members[1], $members[2]], $trainer, hours: 2);

        $this->assertSame('3900.00', (string) $r->amount);
        $this->assertSame('1950.00', (string) $r->hourly_rate);
        $this->assertEquals(['mode' => 'with_trainer', 'people' => 3, 'hours' => 2, 'per_hour' => 1950, 'per_person_per_hour' => 650, 'amount' => 3900, 'free' => []], $r->pricing);
        $this->assertSame('3 คน × ฿650/คน/ชม. × 2 ชม. (มี Trainer)', $r->priceLabel());

        $gym->update(['pricing' => PriceTable::flat(99)->toArray()]);
        $this->assertSame('3900.00', (string) $r->fresh()->amount);
    }

    #[Test]
    public function a_privileged_group_without_a_trainer_is_priced_on_the_no_trainer_table(): void
    {
        $gym = $this->realGym();
        $a = $this->makeMember($gym, attributes: ['can_book_without_trainer' => true]);
        $b = $this->makeMember($gym, attributes: ['can_book_without_trainer' => true]);

        $r = $this->bookAsTrainee($gym, $a, [$b], null);

        $this->assertSame('950.00', (string) $r->amount);
        $this->assertSame('without_trainer', $r->pricing['mode']);
    }

    #[Test]
    public function the_booking_page_quotes_live_as_people_and_trainer_choice_change(): void
    {
        $gym = $this->realGym();
        $this->makeAvailableTrainer($gym);
        $me = $this->makeMember($gym, attributes: ['can_book_without_trainer' => true]);
        $friend = $this->makeMember($gym);
        $friend->user->update(['phone' => '0812345678']);

        $page = Livewire::actingAs($me->user)->test(BookingWizard::class)
            ->call('selectDay', '2026-10-16')->call('setHours', 2);

        $this->assertSame(2100.0, $page->instance()->quote->amount);

        $page->call('chooseTrainer', null);
        $this->assertSame(1000.0, $page->instance()->quote->amount, '1 คน ไม่มี Trainer 500 × 2 ชม.');

        $page->set('lookup', '0812345678')->call('addMember');
        $this->assertSame(1550.0 * 2, $page->instance()->quote->amount, 'เพื่อนไม่มีสิทธิ์ กลับมาคิดแบบมี Trainer');

        $page->assertSee('ตารางราคา')->assertSee('คนที่ 6 ขึ้นไป เพิ่มคนละ ฿530/ชม.');
    }

    #[Test]
    public function an_admin_edits_the_table_per_branch_and_customers_get_the_new_price(): void
    {
        $gym = $this->realGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $this->actingAs($this->makeAdmin($gym));

        Livewire::test(EditBranch::class, ['record' => $gym->id])
            ->fillForm([
                'pricing.with_trainer.tiers' => [['price' => 1200], ['price' => 1800]],
                'pricing.with_trainer.extra_per_person' => 600,
                'pricing.without_trainer.tiers' => [['price' => 600]],
                'pricing.without_trainer.extra_per_person' => 450,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $table = $gym->fresh()->priceTable();
        $this->assertSame(1800.0 + 600, $table->perHour(3, true));
        $this->assertSame(600.0 + 450 * 2, $table->perHour(3, false));

        $r = $this->bookAsTrainee($gym->refresh(), $this->makeMember($gym), [], $trainer);
        $this->assertSame('1200.00', (string) $r->amount);

        // เปิดหน้าแก้ไขอีกครั้งต้องเห็นค่าที่บันทึกไว้ ไม่ใช่ค่าตั้งต้น
        Livewire::test(EditBranch::class, ['record' => $gym->id])
            ->assertFormSet(['pricing.with_trainer.extra_per_person' => 600]);
    }

    #[Test]
    public function a_table_with_zero_for_one_person_counts_as_not_set(): void
    {
        $this->assertFalse(PriceTable::flat(0)->isSet());
        $this->assertFalse(PriceTable::fromArray(['with_trainer' => ['tiers' => [1000]], 'without_trainer' => ['tiers' => []]])->isSet());
        $this->assertTrue(PriceTable::for($this->realGym())->isSet());
    }
}
