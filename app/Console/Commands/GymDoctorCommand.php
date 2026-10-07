<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Enums\RefundStatus;
use App\Enums\RequestStatus;
use App\Enums\ReservationStatus;
use App\Enums\TrainerStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Models\ScheduleTemplate;
use App\Models\Trainer;
use App\Services\Reservations\OpeningHours;
use App\Support\RegistrationRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * ตรวจว่าระบบพร้อมให้จองจริงหรือยัง
 *
 * ปัญหา "จองไม่ได้" บนเซิร์ฟเวอร์ส่วนใหญ่ไม่ใช่บั๊ก แต่เป็นข้อมูลหรือขั้นตอนที่ขาด
 * เช่นยังไม่ได้ migrate ยังไม่ตั้งราคา ไม่มีเวลาเปิด หรือไม่มี Trainer ที่ตั้งเวลาว่างไว้
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
        $this->checkOpenHours();
        $this->checkTrainers();

        $this->section('งานที่ค้างและข้อผิดพลาดล่าสุด');
        $this->checkPending();
        $this->checkScheduler();
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
            : $this->broken("เขตเวลาเป็น {$tz} เวลาจองจะคลาดจากเวลาจริง", 'ตั้ง APP_TIMEZONE=Asia/Bangkok ใน .env แล้ว php artisan optimize');
    }

    protected function checkSettings(): void
    {
        $this->info(sprintf(
            '  กันเวลารอชำระ %d นาที%s | กลุ่มไม่มี Trainer: %s | ชำระเงิน: %s | สมัครเองได้: %s | อนุมัติการสมัคร: %s',
            (int) config('gym.reservation.hold_minutes', 30),
            config('gym.reservation.hold_ends_at_start', true) ? ' (หรือถึงเวลาเริ่ม)' : '',
            config('gym.reservation.no_trainer_rule', 'all') === 'any' ? 'มีสิทธิ์อย่างน้อยหนึ่งคน' : 'ทุกคนต้องมีสิทธิ์',
            config('gym.payments.driver', 'manual') === 'omise' ? 'Omise' : 'แอดมินบันทึกเอง',
            RegistrationRules::publicRegistrationOpen() ? 'ใช่' : 'ไม่',
            RegistrationRules::memberRegistrationNeedsApproval() ? 'ต้องอนุมัติ' : 'ไม่ต้อง',
        ));

        if (config('gym.payments.driver') === 'omise' && (blank(config('services.omise.public_key')) || blank(config('services.omise.secret_key')))) {
            $this->broken('เลือกชำระผ่าน Omise แต่ยังไม่ได้ใส่คีย์ ผู้จองจะสร้าง QR ไม่ได้', 'ใส่ OMISE_PUBLIC_KEY และ OMISE_SECRET_KEY ใน .env หรือกลับไปใช้ GYM_PAYMENT_DRIVER=manual');
        }
    }

    protected function checkBranches(): void
    {
        $count = Branch::active()->count();

        if ($count === 0) {
            $this->broken('ไม่มีสาขาที่เปิดใช้งาน', 'php artisan db:seed --class=ProductionSeeder --force');

            return;
        }

        $this->ok("สาขาที่เปิดใช้ {$count} สาขา");

        foreach (Branch::active()->get() as $branch) {
            (float) $branch->hourly_rate > 0
                ? $this->ok("{$branch->name}: ราคา ฿".number_format((float) $branch->hourly_rate, 0).'/ชม. ไม่เกิน '.$branch->max_trainees.' คน จองล่วงหน้า '.$branch->booking_window_days.' วัน')
                : $this->broken("{$branch->name}: ยังไม่ตั้งราคาต่อชั่วโมง จองไม่ได้", 'หลังบ้าน → สาขา → แก้ไข → ราคาต่อชั่วโมง');

            if (config('gym.payments.driver', 'manual') === 'manual' && blank($branch->payment_instructions)) {
                $this->caution("{$branch->name}: ยังไม่ได้เขียนวิธีชำระเงิน ผู้จองจะไม่รู้ว่าโอนไปที่ไหน", 'หลังบ้าน → สาขา → แก้ไข → วิธีชำระเงิน');
            }
        }
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
                $this->broken("{$branch->name}: ยังไม่มีตารางเวลาเปิด ไม่มีช่วงเวลาให้จอง", 'หลังบ้าน → ตารางเวลาเปิด → สร้าง หนึ่งแถวต่อหนึ่งวัน');

                continue;
            }

            $names = $days->map(fn ($d) => ScheduleTemplate::DAY_NAMES[$d] ?? $d)->implode(' ');
            $missing = collect(range(0, 6))->diff($days)->map(fn ($d) => ScheduleTemplate::DAY_NAMES[$d])->implode(' ');

            $days->count() === 7
                ? $this->ok("{$branch->name}: เปิดครบ 7 วัน")
                : $this->caution("{$branch->name}: เปิดเฉพาะ {$names} — วัน {$missing} จองไม่ได้", 'ถ้าตั้งใจเปิดวันอื่นด้วย ต้องสร้างตารางเวลาเปิดแยกทีละวัน');
        }
    }

    /** เวลาเปิดตามตารางหลังหักวันปิดพิเศษ ใน 7 วันข้างหน้า */
    protected function checkOpenHours(): void
    {
        $hours = app(OpeningHours::class);

        foreach (Branch::active()->get() as $branch) {
            $total = collect(range(0, 6))->sum(fn ($i) => count($hours->slotsOn($branch, now()->addDays($i))));

            $total > 0
                ? $this->ok("{$branch->name}: 7 วันข้างหน้าเปิดให้จอง {$total} ชั่วโมง")
                : $this->broken("{$branch->name}: 7 วันข้างหน้าไม่มีชั่วโมงเปิดเลย", 'ตรวจตารางเวลาเปิดและวันพิเศษในหลังบ้าน');
        }
    }

    protected function checkTrainers(): void
    {
        $approved = Trainer::where('status', TrainerStatus::Approved->value)->with('availabilities')->get();
        $ready = $approved->filter(fn (Trainer $t) => $t->canTakeReservations() && $t->availabilities->isNotEmpty());
        $privileged = Member::where('can_book_without_trainer', true)->count();

        $this->ok('Trainer อนุมัติแล้ว '.$approved->count().' คน สมาชิก '.Member::count().' คน มีสิทธิ์ไม่มี Trainer '.$privileged.' คน');

        if ($ready->isEmpty()) {
            $this->broken('ไม่มี Trainer ที่เปิดรับงานและตั้งเวลาว่างไว้ ลูกเทรนจะไม่มี Trainer ให้เลือก', 'ให้ Trainer เข้าเมนู "เวลาว่าง" ตั้งเวลาที่พร้อมรับงาน');

            return;
        }

        $this->ok('Trainer พร้อมรับงาน '.$ready->count().' คน');

        $noHours = $approved->filter(fn (Trainer $t) => $t->availabilities->isEmpty());

        if ($noHours->isNotEmpty()) {
            $this->caution('Trainer ยังไม่ตั้งเวลาว่าง '.$noHours->count().' คน: '.$noHours->map(fn ($t) => $t->user?->name)->implode(', '), 'ลูกเทรนจะไม่เห็นคนเหล่านี้จนกว่าจะตั้งเวลาว่าง');
        }
    }

    protected function checkPending(): void
    {
        $registrations = Member::where('status', MemberStatus::Pending->value)->count();

        $registrations === 0
            ? $this->ok('ไม่มีการสมัครค้างอนุมัติ')
            : $this->caution("มีคนสมัครรออนุมัติ {$registrations} คน ยังจองไม่ได้จนกว่าจะอนุมัติ", 'หลังบ้าน → สมาชิก → แท็บรออนุมัติ');

        $requests = ReservationRequest::where('status', RequestStatus::Pending->value)->count();
        $review = Payment::where('needs_review', true)->count();
        $refunds = Reservation::whereIn('refund_status', [RefundStatus::Pending->value, RefundStatus::Failed->value])->count();

        $requests === 0
            ? $this->ok('ไม่มีคำขอเลื่อนหรือเปลี่ยน Trainer ค้าง')
            : $this->caution("มีคำขอรอพิจารณา {$requests} รายการ", 'หลังบ้าน → การจอง → คำขอ');

        if ($review > 0) {
            $this->caution("มีการชำระเงินที่ต้องตรวจสอบ {$review} รายการ (อาจต้องคืนเงิน)", 'หลังบ้าน → การจอง → การชำระเงิน');
        }

        if ($refunds > 0) {
            $this->caution("มีการคืนเงินที่ยังไม่สำเร็จ {$refunds} รายการ", 'หลังบ้าน → การจอง → แท็บคืนเงินค้าง');
        }
    }

    /**
     * รายการรอชำระที่เลยเวลามานานแล้วยังไม่ถูกปิด แปลว่า schedule:run ไม่ได้ทำงาน
     * ระบบยังกันจองซ้อนได้ แต่ผู้ใช้จะเห็นสถานะค้างเป็น "รอชำระเงิน" และไม่ได้รับแจ้งว่าหมดอายุ
     */
    protected function checkScheduler(): void
    {
        $stuck = Reservation::where('status', ReservationStatus::PendingPayment->value)
            ->where('hold_expires_at', '<', now()->subMinutes(10))
            ->count();

        $stuck === 0
            ? $this->ok('ไม่มีรายการรอชำระที่หมดเวลาค้างอยู่')
            : $this->caution("มีรายการรอชำระที่หมดเวลาแล้วแต่ยังไม่ถูกปิด {$stuck} รายการ ตัวตั้งเวลาอาจไม่ได้ทำงาน", 'ตั้ง Scheduled Task ให้รัน php artisan schedule:run ทุก 1 นาที (deploy.ps1 -InstallScheduler)');
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
        $when = \Carbon\Carbon::parse($last[1]);

        // ข้อผิดพลาดเก่าที่ไม่เกิดซ้ำแล้วไม่ใช่ปัญหาตอนนี้ แจ้งไว้เป็นประวัติเฉย ๆ ไม่นับเป็นคำเตือน
        // log ก่อนแก้ timezone บันทึกเป็น UTC จึงเผื่อเวลาไว้หนึ่งวันเต็ม
        if ($when->lt(now()->subDay())) {
            $this->ok('ไม่มีข้อผิดพลาดใหม่ใน 24 ชั่วโมง (ล่าสุดเมื่อ '.$when->format('d/m/Y H:i').')');

            return;
        }

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
