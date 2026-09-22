<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\ScheduleException;
use App\Models\WorkoutSession;
use App\Services\SessionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class ScheduleGenerationTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    protected SessionGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(SessionGenerator::class);

        // ตรึงเวลาให้ผลลัพธ์คงที่: 2026-09-21 เป็นวันจันทร์
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(1, 0));
    }

    protected function nextMonday()
    {
        return now()->copy()->next(\Carbon\Carbon::MONDAY);
    }

    #[Test]
    public function it_slices_an_opening_window_into_hourly_sessions(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '21:00']);

        $monday = $this->nextMonday();
        $this->generator->generateForDate($branch, $monday);

        $sessions = WorkoutSession::orderBy('starts_at')->get();

        $this->assertCount(3, $sessions, '18:00-21:00 ต้องได้ 3 รอบ');
        $this->assertSame('18:00', $sessions[0]->starts_at->format('H:i'));
        $this->assertSame('19:00', $sessions[0]->ends_at->format('H:i'));
        $this->assertSame('20:00', $sessions[2]->starts_at->format('H:i'));
        $this->assertSame(5, $sessions[0]->capacity);
    }

    #[Test]
    public function it_drops_a_trailing_partial_slot(): void
    {
        $branch = $this->makeBranch();
        // 18:00-20:30 ลงตัวแค่ 2 รอบ ครึ่งชั่วโมงที่เหลือไม่นับเป็นรอบ
        $this->makeTemplate($branch, ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '20:30']);

        $this->generator->generateForDate($branch, $this->nextMonday());

        $this->assertSame(2, WorkoutSession::count());
    }

    #[Test]
    public function running_the_generator_twice_creates_no_duplicates(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1]);
        $monday = $this->nextMonday();

        $first = $this->generator->generateForDate($branch, $monday);
        $second = $this->generator->generateForDate($branch, $monday);

        $this->assertSame(3, $first['created']);
        $this->assertSame(0, $second['created'], 'รอบสองต้องไม่สร้างซ้ำ');
        $this->assertSame(3, $second['skipped']);
        $this->assertSame(3, WorkoutSession::count());
    }

    #[Test]
    public function a_holiday_closes_the_whole_day(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1]);
        $monday = $this->nextMonday();

        ScheduleException::create([
            'branch_id' => $branch->id,
            'date' => $monday->toDateString(),
            'type' => ScheduleException::TYPE_CLOSED,
            'reason' => 'วันหยุดนักขัตฤกษ์',
        ]);

        $this->generator->generateForDate($branch, $monday);

        $this->assertSame(0, WorkoutSession::count());
    }

    #[Test]
    public function custom_hours_replace_the_regular_schedule(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '21:00']);
        $monday = $this->nextMonday();

        ScheduleException::create([
            'branch_id' => $branch->id,
            'date' => $monday->toDateString(),
            'type' => ScheduleException::TYPE_CUSTOM_HOURS,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'capacity' => 3,
            'reason' => 'ซ้อมใหญ่ช่วงเช้า',
        ]);

        $this->generator->generateForDate($branch, $monday);

        $sessions = WorkoutSession::orderBy('starts_at')->get();

        $this->assertCount(2, $sessions);
        $this->assertSame('08:00', $sessions[0]->starts_at->format('H:i'));
        $this->assertSame(3, $sessions[0]->capacity, 'ความจุของวันนั้นต้องถูกแทนที่ด้วย');
    }

    #[Test]
    public function a_special_opening_adds_slots_on_top_of_the_regular_schedule(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '21:00']);
        $monday = $this->nextMonday();

        ScheduleException::create([
            'branch_id' => $branch->id,
            'date' => $monday->toDateString(),
            'type' => ScheduleException::TYPE_SPECIAL_OPEN,
            'start_time' => '06:00',
            'end_time' => '08:00',
        ]);

        $this->generator->generateForDate($branch, $monday);

        $this->assertSame(5, WorkoutSession::count(), '3 รอบปกติ + 2 รอบพิเศษ');
    }

    #[Test]
    public function a_template_outside_its_effective_window_is_ignored(): void
    {
        $branch = $this->makeBranch();
        $monday = $this->nextMonday();

        $this->makeTemplate($branch, [
            'day_of_week' => 1,
            'effective_from' => $monday->copy()->addWeeks(2)->toDateString(),
        ]);

        $this->generator->generateForDate($branch, $monday);

        $this->assertSame(0, WorkoutSession::count());
    }

    #[Test]
    public function it_never_creates_sessions_in_the_past(): void
    {
        $branch = $this->makeBranch();
        // วันนี้คือจันทร์ 01:00 กฎเปิด 00:00-03:00 จึงมีรอบที่ผ่านไปแล้วปนอยู่
        $this->makeTemplate($branch, ['day_of_week' => 1, 'start_time' => '00:00', 'end_time' => '03:00']);

        $this->generator->generateForDate($branch, now());

        $this->assertSame(2, WorkoutSession::count(), 'รอบ 00:00 ที่ผ่านไปแล้วต้องไม่ถูกสร้าง');
        $this->assertTrue(WorkoutSession::min('starts_at') >= now()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function closing_a_day_afterwards_cancels_the_sessions_already_created(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1]);
        $monday = $this->nextMonday();

        $this->generator->generateForDate($branch, $monday);
        $cancelled = $this->generator->closeDay($branch, $monday, 'น้ำท่วม');

        $this->assertSame(3, $cancelled);
        $this->assertSame(3, WorkoutSession::where('status', SessionStatus::Cancelled->value)->count());
        $this->assertSame('น้ำท่วม', WorkoutSession::first()->close_reason);
    }

    #[Test]
    public function generating_a_range_covers_every_matching_weekday(): void
    {
        $branch = $this->makeBranch();
        $this->makeTemplate($branch, ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '21:00']);

        // วันนี้เป็นวันจันทร์ และช่วงนับรวมปลายทั้งสองข้าง จึงเจอวันจันทร์ 4 ครั้ง (วันที่ 0, 7, 14, 21)
        $result = $this->generator->generateForBranch($branch, now(), now()->addDays(21));

        $this->assertSame(12, $result['created'], '4 วันจันทร์ x 3 รอบ');
    }
}
