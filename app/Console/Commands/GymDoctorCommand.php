<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Enums\TrainerStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Member;
use App\Models\ScheduleTemplate;
use App\Models\Trainer;
use App\Models\WorkoutSession;
use App\Support\BookingRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * ตรวจว่าระบบพร้อมให้จองจริงหรือยัง
 *
 * ปัญหา "จองไม่ได้" บนเซิร์ฟเวอร์ส่วนใหญ่ไม่ใช่บั๊ก แต่เป็นข้อมูลหรือขั้นตอนที่ขาด
 * เช่นยังไม่ได้ migrate ไม่มีตารางเวลาเปิด หรือไม่มีรอบให้จอง
 * คำสั่งนี้อ่านอย่างเดียว ไม่แก้อะไร รันบนเครื่องจริงได้ปลอดภัย
 */
class GymDoctorCommand extends Command
{
    protected $signature = 'gym:doctor';

    protected $description = 'ตรวจความพร้อมของระบบจอง บอกสิ่งที่ขาดและวิธีแก้';

    protected int $failures = 0;

    protected int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>ตรวจความพร้อมของระบบจอง</>');

        $this->section('ฐานข้อมูลและเวลา');
        $this->checkMigrations();
        $this->checkTimezone();

        $this->section('ตั้งค่าการจอง');
        $this->checkSettings();

        $this->section('ข้อมูลที่ต้องมีก่อนจองได้');
        $this->checkBranches();
        $this->checkTemplates();
        $this->checkSessions();
        $this->checkPeople();

        $this->section('งานที่ค้างและข้อผิดพลาดล่าสุด');
        $this->checkPending();
        $this->checkLog();

        $this->newLine();

        if ($this->failures > 0) {
            $this->line("<fg=red;options=bold>ไม่ผ่าน {$this->failures} ข้อ</> เตือน {$this->warnings} ข้อ — แก้ข้อที่ไม่ผ่านก่อน แล้วรันคำสั่งนี้ซ้ำ");

            return self::FAILURE;
        }

        $this->line("<fg=green;options=bold>พร้อมให้จอง</> เตือน {$this->warnings} ข้อ");

        return self::SUCCESS;
    }

    protected function checkMigrations(): void
    {
        $files = collect(File::files(database_path('migrations')))
            ->map(fn ($f) => $f->getFilenameWithoutExtension());

        $ran = DB::table('migrations')->pluck('migration');
        $missing = $files->diff($ran);

        $missing->isEmpty()
            ? $this->ok('migration ครบทุกตัว')
            : $this->broken('ยังไม่ได้ migrate '.$missing->count().' ตัว: '.$missing->implode(', '), 'php artisan migrate --force');
    }

    protected function checkTimezone(): void
    {
        $tz = config('app.timezone');

        $tz === 'Asia/Bangkok'
            ? $this->ok('เขตเวลา Asia/Bangkok ตอนนี้ '.now()->format('d/m/Y H:i'))
            : $this->broken("เขตเวลาเป็น {$tz} รอบที่เลยเวลาแล้วจะยังจองได้", 'ตั้ง APP_TIMEZONE=Asia/Bangkok ใน .env แล้ว php artisan optimize');
    }

    protected function checkSettings(): void
    {
        $this->info(sprintf(
            '  เครดิต: %s | อนุมัติการจอง: %s | สมัครเองได้: %s | อนุมัติการสมัคร: %s | จองล่วงหน้าเองได้ %d วัน',
            BookingRules::creditsRequired() ? 'เปิด (ต้องมีแพ็กเกจ)' : 'ปิด',
            match (config('gym.booking.approval', BookingRules::APPROVAL_NONE)) {
                BookingRules::APPROVAL_ALL => 'ทุกการจอง',
                BookingRules::APPROVAL_SELF => 'เฉพาะที่สมาชิกจองเอง',
                default => 'ไม่ต้อง',
            },
            BookingRules::publicRegistrationOpen() ? 'ใช่' : 'ไม่',
            BookingRules::memberRegistrationNeedsApproval() ? 'ต้องอนุมัติ' : 'ไม่ต้อง',
            BookingRules::selfAdvanceDays(),
        ));
    }

    protected function checkBranches(): void
    {
        $count = Branch::active()->count();

        $count > 0
            ? $this->ok("สาขาที่เปิดใช้ {$count} สาขา")
            : $this->broken('ไม่มีสาขาที่เปิดใช้งาน', 'php artisan db:seed --class=ProductionSeeder --force');
    }

    protected function checkTemplates(): void
    {
        foreach (Branch::active()->get() as $branch) {
            $days = ScheduleTemplate::where('branch_id', $branch->id)
                ->where('is_active', true)
                ->pluck('day_of_week')
                ->unique()
                ->sort()
                ->values();

            if ($days->isEmpty()) {
                $this->broken("{$branch->name}: ยังไม่มีตารางเวลาเปิด ไม่มีทางสร้างรอบได้", 'หลังบ้าน → ตารางเวลาเปิด → สร้าง หนึ่งแถวต่อหนึ่งวัน');

                continue;
            }

            $names = $days->map(fn ($d) => ScheduleTemplate::DAY_NAMES[$d] ?? $d)->implode(' ');
            $missing = collect(range(0, 6))->diff($days)->map(fn ($d) => ScheduleTemplate::DAY_NAMES[$d])->implode(' ');

            $days->count() === 7
                ? $this->ok("{$branch->name}: เปิดครบ 7 วัน")
                : $this->caution("{$branch->name}: เปิดเฉพาะ {$names} — ไม่มีรอบวัน {$missing}", 'ถ้าตั้งใจเปิดวันอื่นด้วย ต้องสร้างตารางเวลาเปิดแยกทีละวัน');
        }
    }

    protected function checkSessions(): void
    {
        $until = now()->addDays(BookingRules::selfAdvanceDays())->endOfDay();

        $upcoming = WorkoutSession::query()
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', $until);

        $total = (clone $upcoming)->count();
        $open = (clone $upcoming)->where('status', SessionStatus::Open->value)->count();
        $selfBookable = (clone $upcoming)
            ->where('status', SessionStatus::Open->value)
            ->where('mode', SessionMode::Shared->value)
            ->whereColumn('booked_count', '<', 'capacity')
            ->count();

        if ($total === 0) {
            $this->broken('ไม่มีรอบเลยใน '.BookingRules::selfAdvanceDays().' วันข้างหน้า', 'php artisan sessions:generate หรือกด "สร้างรอบล่วงหน้า" ในหลังบ้าน');
        } elseif ($selfBookable === 0) {
            $this->broken("มี {$total} รอบ แต่ไม่มีรอบที่สมาชิกจองเองได้ (เปิดอยู่ {$open} รอบ ที่เหลือเต็มหรือเป็นรอบเหมา)", 'ตรวจสถานะรอบและโหมดรอบในหลังบ้าน');
        } else {
            $this->ok("รอบใน ".BookingRules::selfAdvanceDays()." วันข้างหน้า {$total} รอบ สมาชิกจองเองได้ {$selfBookable} รอบ");
        }

        $last = WorkoutSession::max('starts_at');

        if ($last && now()->diffInDays($last, false) < 7) {
            $this->caution('รอบล่วงหน้ามีถึง '.\Carbon\Carbon::parse($last)->format('d/m/Y').' เท่านั้น', 'ตั้ง Scheduled Task ให้ php artisan schedule:run ทุก 1 นาที ระบบจะสร้างรอบเพิ่มเองทุกคืน');
        }
    }

    protected function checkPeople(): void
    {
        $trainers = Trainer::where('status', TrainerStatus::Approved->value)->count();
        $members = Member::count();

        $this->ok("เทรนเนอร์ที่อนุมัติแล้ว {$trainers} คน สมาชิก {$members} คน");

        if (BookingRules::creditsRequired()) {
            $withoutCredit = Member::whereDoesntHave('packages', fn ($q) => $q->active()->whereColumn('credits_used', '<', 'credits_total'))->count();

            $withoutCredit === 0
                ? $this->ok('สมาชิกทุกคนมีเครดิตเหลือ')
                : $this->caution("สมาชิก {$withoutCredit} คนไม่มีเครดิตเหลือ จะเจอ \"เครดิตคงเหลือไม่พอ\"", 'ออกแพ็กเกจให้ หรือปิดระบบเครดิตด้วย GYM_REQUIRE_CREDITS=false');
        }
    }

    protected function checkPending(): void
    {
        $registrations = Member::where('status', \App\Enums\MemberStatus::Pending->value)->count();

        $registrations === 0
            ? $this->ok('ไม่มีการสมัครค้างอนุมัติ')
            : $this->caution("มีคนสมัครรออนุมัติ {$registrations} คน ยังจองไม่ได้จนกว่าจะอนุมัติ", 'หลังบ้าน → สมาชิก → แท็บรออนุมัติ');

        $pending = Booking::where('status', BookingStatus::Pending->value)->count();

        $pending === 0
            ? $this->ok('ไม่มีคำขอจองค้างอนุมัติ')
            : $this->caution("มีคำขอจองรออนุมัติ {$pending} รายการ", 'หลังบ้าน → การจอง → แท็บรออนุมัติ');
    }

    protected function checkLog(): void
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            $this->ok('ยังไม่มีข้อผิดพลาดบันทึกไว้');

            return;
        }

        if (! is_writable($path)) {
            $this->broken('เขียนไฟล์ log ไม่ได้ ข้อผิดพลาดต่อจากนี้จะหายไปเงียบ ๆ', 'ให้สิทธิ์เขียนโฟลเดอร์ storage แก่ผู้ใช้ที่รันเว็บ');

            return;
        }

        // อ่านแค่ท้ายไฟล์ ไฟล์ log บนเครื่องจริงใหญ่ได้หลายร้อย MB
        $tail = $this->tail($path, 200_000);
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|EMERGENCY): (.{0,220})/m', $tail, $m, PREG_SET_ORDER);

        if ($m === []) {
            $this->ok('ไม่มีข้อผิดพลาดในช่วงท้ายของ log');

            return;
        }

        $last = end($m);
        $this->caution('ข้อผิดพลาดล่าสุด ['.$last[1].'] '.trim($last[3]), 'ดูทั้งหมดที่ storage/logs/laravel.log');
    }

    protected function tail(string $path, int $bytes): string
    {
        $size = filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - $bytes));
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    // --- การแสดงผล ---

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    protected function ok(string $message): void
    {
        $this->line("  <fg=green>[ผ่าน]</> {$message}");
    }

    protected function caution(string $message, ?string $fix = null): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>[เตือน]</> {$message}");

        if ($fix) {
            $this->line("          <fg=gray>วิธีแก้: {$fix}</>");
        }
    }

    protected function broken(string $message, ?string $fix = null): void
    {
        $this->failures++;
        $this->line("  <fg=red>[ไม่ผ่าน]</> {$message}");

        if ($fix) {
            $this->line("          <fg=gray>วิธีแก้: {$fix}</>");
        }
    }
}
