<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\MemberGroups\Pages\ListMemberGroups;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Trainers\Pages\ListTrainers;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsReservations;
use Tests\TestCase;

/**
 * Filament ส่ง query เข้า closure ของตัวกรองตาม "ชื่อพารามิเตอร์"
 * ถ้าเขียน fn (Builder $q) แทน fn (Builder $query) จะได้ query เปล่าที่ไม่มี model
 * แล้วหน้าพังทันทีที่แอดมินเปิดตัวกรองนั้น เคยพังแบบนี้มาแล้วหกตัวพร้อมกัน
 */
class AdminFiltersTest extends TestCase
{
    use BuildsReservations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00'));
        Notification::fake();

        $branch = $this->makeBranch();

        $this->actingAs(User::create([
            'branch_id' => $branch->id, 'first_name' => 'แอด', 'last_name' => 'มิน',
            'email' => 'admin@example.test', 'password' => 'password', 'role' => UserRole::Admin,
        ])->refresh());
    }

    public static function filters(): array
    {
        return [
            'การจอง: วันที่ใช้งาน' => [ListReservations::class, 'date'],
            'กลุ่มลูกทีม: ตั้งจำนวนเอง' => [ListMemberGroups::class, 'has_override'],
            'เทรนเนอร์: ใบรับรองใกล้หมด' => [ListTrainers::class, 'certification_expiring'],
            'สมาชิก: มีสิทธิ์ไม่มี Trainer' => [ListMembers::class, 'no_trainer'],
        ];
    }

    #[Test]
    #[DataProvider('filters')]
    public function every_custom_filter_can_be_switched_on(string $page, string $filter): void
    {
        Livewire::test($page)
            ->filterTable($filter)
            ->assertSuccessful();
    }

    /**
     * ตัวกรองบางตัวไม่พังแต่เงียบหายแทน เช่นตัวที่ใช้แค่ where ธรรมดา
     * query เปล่าที่ได้มารับ where ได้ แต่ไม่ใช่ query ของตาราง ตัวกรองจึงไม่กรองอะไรเลย
     * เทสต์ "เปิดได้ไม่พัง" อย่างเดียวจับกรณีนี้ไม่ได้ ต้องตรวจชื่อพารามิเตอร์ตรง ๆ
     */
    #[Test]
    public function query_closures_always_name_their_parameter_query(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path('Filament')) as $file) {
            foreach (file($file->getPathname()) as $i => $line) {
                if (preg_match('/(->query|modifyQueryUsing)\(\s*(static\s+)?(fn|function)\s*\(\s*Builder\s+\$(?!query\b)\w+/', $line)) {
                    $offenders[] = $file->getRelativePathname().':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'ต้องตั้งชื่อพารามิเตอร์ว่า $query ไม่งั้น Filament ส่ง query เปล่ามาให้');
    }

    #[Test]
    public function the_no_trainer_filter_actually_filters(): void
    {
        $branch = $this->makeBranch();
        $privileged = $this->makeMember($branch, attributes: ['can_book_without_trainer' => true]);
        $plain = $this->makeMember($branch);

        Livewire::test(ListMembers::class)
            ->set('activeTab', 'all')
            ->filterTable('no_trainer')
            ->assertCanSeeTableRecords([$privileged])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    #[Test]
    public function the_date_filter_actually_filters(): void
    {
        $gym = $this->makeGym();
        $trainer = $this->makeAvailableTrainer($gym);
        $tomorrow = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::parse('2026-10-08 18:00'));
        $later = $this->bookAsTrainee($gym, $this->makeMember($gym), [], $trainer, CarbonImmutable::parse('2026-10-10 18:00'));

        Livewire::test(ListReservations::class)
            ->filterTable('date', ['on' => '2026-10-08'])
            ->assertCanSeeTableRecords([$tomorrow])
            ->assertCanNotSeeTableRecords([$later]);
    }
}
