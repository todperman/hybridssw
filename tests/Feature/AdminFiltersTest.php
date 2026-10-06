<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\MemberGroups\Pages\ListMemberGroups;
use App\Filament\Resources\Trainers\Pages\ListTrainers;
use App\Filament\Resources\WorkoutSessions\Pages\ListWorkoutSessions;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

/**
 * Filament ส่ง query เข้า closure ของตัวกรองตาม "ชื่อพารามิเตอร์"
 * ถ้าเขียน fn (Builder $q) แทน fn (Builder $query) จะได้ query เปล่าที่ไม่มี model
 * แล้วหน้าพังทันทีที่แอดมินเปิดตัวกรองนั้น เคยพังแบบนี้มาแล้วหกตัวพร้อมกัน
 */
class AdminFiltersTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(8, 0));

        $branch = $this->makeBranch();

        $this->actingAs(User::create([
            'branch_id' => $branch->id, 'first_name' => 'แอด', 'last_name' => 'มิน',
            'email' => 'admin@example.test', 'password' => 'password', 'role' => UserRole::Admin,
        ])->refresh());
    }

    public static function filters(): array
    {
        return [
            'การจอง: เฉพาะรอบวันนี้' => [ListBookings::class, 'today'],
            'การจอง: รอยืนยันสิทธิ์' => [ListBookings::class, 'awaiting_confirmation'],
            'กลุ่มลูกทีม: ตั้งจำนวนเอง' => [ListMemberGroups::class, 'has_override'],
            'เทรนเนอร์: ใบรับรองใกล้หมด' => [ListTrainers::class, 'certification_expiring'],
            'รอบ: ที่ยังไม่ถึง' => [ListWorkoutSessions::class, 'upcoming'],
            'รอบ: มีคิวสำรอง' => [ListWorkoutSessions::class, 'has_waitlist'],
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
    public function the_waitlist_filter_actually_filters(): void
    {
        $branch = $this->makeBranch(['default_capacity' => 1]);
        $trainer = $this->makeTrainer($branch);
        $busy = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $quiet = $this->makeSession($branch, ['starts_at' => now()->setTime(19, 0)]);

        $bookings = app(BookingService::class);
        $bookings->book($busy, $this->makeMember($branch, $trainer), $trainer);
        $bookings->book($busy, $this->makeMember($branch, $trainer), $trainer);

        Livewire::test(ListWorkoutSessions::class)
            ->filterTable('has_waitlist')
            ->assertCanSeeTableRecords([$busy])
            ->assertCanNotSeeTableRecords([$quiet]);
    }

    #[Test]
    public function the_today_filter_actually_filters(): void
    {
        $branch = $this->makeBranch();
        $trainer = $this->makeTrainer($branch);
        $today = $this->makeSession($branch, ['starts_at' => now()->setTime(18, 0)]);
        $later = $this->makeSession($branch, ['starts_at' => now()->addDays(3)->setTime(18, 0)]);

        $a = app(BookingService::class)->book($today, $this->makeMember($branch, $trainer), $trainer);
        $b = app(BookingService::class)->book($later, $this->makeMember($branch, $trainer), $trainer);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'all')
            ->filterTable('today')
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }
}
