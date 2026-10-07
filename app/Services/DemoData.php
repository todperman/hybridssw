<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TeamMember;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Reservations\Availability;
use App\Services\Reservations\ReservationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ข้อมูลตัวอย่างบนเซิร์ฟเวอร์จริง ใช้ลองระบบก่อนเปิดให้บริการ
 *
 * ทุกบัญชีใช้อีเมลโดเมน DOMAIN ซึ่งเป็นโดเมนสงวนที่ไม่มีอยู่จริง จึงแยกออกจากข้อมูลจริงได้เสมอ
 * และ remove() ลบทิ้งได้หมดในคำสั่งเดียว
 *
 * การจองทุกรายการสร้างผ่าน ReservationService / PaymentService ตัวเดียวกับที่ผู้ใช้จริงใช้
 * ข้อมูลจึงเหมือนของจริงทุกอย่าง รวมทั้งประวัติการเปลี่ยนแปลงและแจ้งเตือนในเว็บ
 * ประวัติย้อนหลังสร้างโดยย้อนนาฬิกาของระบบชั่วคราว เพราะระบบไม่ยอมให้จองเวลาที่ผ่านไปแล้ว
 */
class DemoData
{
    public const DOMAIN = 'demo.hybridssw.test';

    /** @var array<int, string> สิ่งที่ทำไป แสดงให้คนรันคำสั่งเห็น */
    public array $log = [];

    protected Branch $branch;

    protected User $admin;

    /** @var Collection<int, Trainer> */
    protected Collection $trainers;

    /** @var Collection<int, Member> */
    protected Collection $members;

    public function __construct(
        protected ReservationService $reservations,
        protected PaymentService $payments,
        protected Availability $availability,
    ) {}

    public static function isDemoEmail(?string $email): bool
    {
        return $email !== null && str_ends_with(strtolower($email), '@'.self::DOMAIN);
    }

    public function exists(): bool
    {
        return User::withTrashed()->where('email', 'like', '%@'.self::DOMAIN)->exists();
    }

    public function create(Branch $branch, string $password, bool $withBookings = true): void
    {
        $this->branch = $branch;

        DB::transaction(function () use ($password) {
            $this->admin = $this->user('admin', 'ผู้ดูแล', 'ตัวอย่าง', UserRole::Admin, null, Str::random(40));
            // ใช้เป็นผู้ทำรายการฝั่งแอดมินในประวัติเท่านั้น ปิดไว้จึงเข้าหลังบ้านไม่ได้ และไม่มีใครรู้รหัส
            $this->admin->update(['is_active' => false]);

            $this->trainers = collect([
                $this->trainer('trainer1', 'ครูต้น', TrainerType::Internal, $password),
                $this->trainer('trainer2', 'ครูฝน', TrainerType::External, $password, [
                    'certification_name' => 'ใบรับรองตัวอย่าง',
                    'certification_expires_at' => now()->addYear(),
                    'contract_starts_at' => now()->subMonths(2),
                    'contract_ends_at' => now()->addYear(),
                ]),
            ]);

            $names = ['มะลิ', 'ต้นข้าว', 'ใบเตย', 'ภูผา', 'ฟ้าใส', 'น้ำหนึ่ง'];
            $this->members = collect($names)->map(fn ($name, $i) => $this->member(
                'member'.($i + 1),
                $name,
                '09900010'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                $password,
                $i < 4 ? $this->trainers[0] : null,
            ));

            $group = MemberGroup::create([
                'trainer_id' => $this->trainers[0]->id,
                'branch_id' => $this->branch->id,
                'name' => 'กลุ่มเย็น (ตัวอย่าง)',
                'description' => 'ลูกทีมตัวอย่างของครูต้น',
            ]);
            $group->members()->attach($this->members->take(3)->pluck('id'), ['joined_at' => now()]);

            // คนเดียวที่เข้าใช้ได้โดยไม่มี Trainer ไว้ลองตัวเลือก "ไม่ใช้ Trainer"
            $this->members[5]->setNoTrainerPrivilege(true, $this->admin, 'ข้อมูลตัวอย่าง: นักกีฬาที่ฝึกเองได้');
        });

        $this->log[] = 'สร้าง Trainer 2 คน ลูกเทรน 6 คน และกลุ่มลูกทีม 1 กลุ่ม';

        if ($withBookings) {
            $this->bookings();
        }
    }

    /** การจองตัวอย่างครบทุกสถานะ ทั้งย้อนหลังและที่กำลังจะถึง */
    protected function bookings(): void
    {
        [$t1, $t2] = [$this->trainers[0], $this->trainers[1]];
        $m = $this->members;
        $today = CarbonImmutable::today();

        // --- ย้อนหลัง ---
        $this->at($today->subDays(13), function () use ($t1, $m, $today) {
            $r = $this->book($today->subDays(12), 2, 18, [$m[0], $m[1], $m[2]], $m[0], $t1, $t1->user);
            $r && $this->pay($r, 'โอนผ่านธนาคาร สลิปตัวอย่าง');
        }, 'ยืนยันแล้วย้อนหลัง (Trainer จองให้ 3 คน 2 ชม.)');

        $this->at($today->subDays(10), function () use ($m, $today) {
            $r = $this->book($today->subDays(9), 1, 7, [$m[5]], $m[5], null, $m[5]->user);
            $r && $this->pay($r);
        }, 'เข้าใช้โดยไม่มี Trainer');

        $this->at($today->subDays(8), function () use ($t2, $m, $today) {
            $r = $this->book($today->subDays(7), 1, 19, [$m[4]], $m[4], $t2, $m[4]->user);

            if ($r) {
                Carbon::setTestNow(now()->addMinutes(31));
                CarbonImmutable::setTestNow(now());
                $this->reservations->expire($r->id);
            }
        }, 'หมดอายุเพราะไม่ชำระ');

        $this->at($today->subDays(6), function () use ($t2, $m, $today) {
            $r = $this->book($today->subDays(5), 1, 18, [$m[3], $m[4]], $m[3], $t2, $m[3]->user);

            if ($r) {
                $this->pay($r);
                $r = $this->reservations->cancelByAdmin($r->refresh(), $this->admin, 'ตัวอย่าง: ยิมปิดซ่อมแอร์');
                $refund = $r->refunds()->latest('id')->first();
                $refund && $this->payments->settleRefund($refund, RefundStatus::Succeeded, $this->admin, 'โอนคืนแล้ว (ตัวอย่าง)');
            }
        }, 'ยกเลิกโดยแอดมินพร้อมคืนเงินสำเร็จ');

        $this->at($today->subDays(5), function () use ($t1, $m, $today) {
            $r = $this->book($today->subDays(3), 1, 17, [$m[1]], $m[1], $t1, $m[1]->user);

            if ($r) {
                $this->pay($r);
                $new = $this->slot($today->subDays(3)->addDay(), 1, 18, $r);
                $new && $this->reservations->reschedule($r->refresh(), $new, $m[1]->user, 'ตัวอย่าง: ติดประชุม');
            }
        }, 'สมาชิกเลื่อนเองก่อนเส้นตาย');

        $this->at($today->subDays(3), function () use ($t1, $t2, $m, $today) {
            $r = $this->book($today->subDays(1), 1, 18, [$m[0], $m[3]], $m[3], $t1, $m[0]->user);

            if ($r) {
                $this->pay($r);
                $this->availability->trainerCovers($t2, $r->starts_at, $r->hours)
                    && $this->reservations->changeTrainer($r->refresh(), $t2, $this->admin, 'ตัวอย่าง: ครูต้นลาป่วย');
            }
        }, 'แอดมินเปลี่ยน Trainer');

        // --- กำลังจะถึง ---
        $r = $this->book($today->addDays(3), 2, 18, $m->take(3)->all(), $m[2], $t1, $t1->user);
        $r && $this->pay($r);
        $this->log[] = ($r ? 'สร้าง' : 'ข้าม').': ยืนยันแล้ว อีก 3 วัน (กลุ่มของครูต้น)';

        $r = $this->book($today->addDays(4), 1, 19, [$m[4]], $m[4], $t2, $m[4]->user);

        if ($r) {
            $this->pay($r);
            $this->reservations->requestTrainerWithdrawal($r->refresh(), $t2->user, 'ตัวอย่าง: ติดสอบใบรับรอง');
        }

        $this->log[] = ($r ? 'สร้าง' : 'ข้าม').': ยืนยันแล้ว อีก 4 วัน พร้อมคำขอยกเลิกงานของครูฝน (รอแอดมินพิจารณา)';

        $r = $this->book($today->addDays(2), 1, 17, [$m[1], $m[3]], $m[1], $t1, $m[1]->user);
        $this->log[] = ($r ? 'สร้าง' : 'ข้าม').': รอชำระเงิน (จะหมดอายุเองใน 30 นาที)';
    }

    /** ย้อนนาฬิกาไปวันนั้นเวลา 09:00 ทำงาน แล้วคืนเวลาเดิมเสมอ */
    protected function at(CarbonImmutable $day, \Closure $work, string $label): void
    {
        // คืนค่าเดิมที่มีอยู่ ไม่ใช่ล้างทิ้ง เผื่อถูกเรียกในเทสต์ที่ตั้งเวลาไว้แล้ว
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($day->setTime(9, 0));
        CarbonImmutable::setTestNow($day->setTime(9, 0));

        try {
            $work();
            $this->log[] = 'สร้าง: '.$label;
        } catch (\Throwable $e) {
            $this->log[] = 'ข้าม: '.$label.' ('.$e->getMessage().')';
        } finally {
            Carbon::setTestNow($previous);
            CarbonImmutable::setTestNow($previous ? CarbonImmutable::instance($previous) : null);
        }
    }

    /** จองช่วงที่ยิมเปิดใกล้เวลาที่ต้องการที่สุด ถ้าวันนั้นไม่มีช่วงว่างคืน null */
    protected function book(CarbonImmutable $day, int $hours, int $preferHour, array $members, Member $payer, ?Trainer $trainer, User $actor): ?Reservation
    {
        $start = $this->slot($day, $hours, $preferHour, null, $trainer);

        if (! $start) {
            return null;
        }

        return $this->reservations->create(
            $this->branch,
            $start,
            $hours,
            array_map(fn (Member $member) => $member->id, $members),
            $payer->id,
            $trainer,
            $actor,
            $actor->trainer ? Reservation::VIA_TRAINER : Reservation::VIA_TRAINEE,
        );
    }

    protected function slot(CarbonImmutable $day, int $hours, int $preferHour, ?Reservation $ignore = null, ?Trainer $trainer = null): ?CarbonImmutable
    {
        $trainer ??= $ignore?->trainer;

        return collect($this->availability->gymStartTimes($this->branch, $day, $hours, $ignore?->id))
            ->filter(fn ($t) => ! $trainer || $this->availability->trainerCovers($trainer, $t, $hours, $ignore?->id))
            ->filter(fn ($t) => ! $ignore || ! $t->equalTo($ignore->starts_at))
            ->sortBy(fn ($t) => abs($t->hour - $preferHour))
            ->first();
    }

    protected function pay(Reservation $reservation, ?string $note = 'ข้อมูลตัวอย่าง'): void
    {
        $this->payments->recordSuccess($reservation, Payment::PROVIDER_MANUAL, $reservation->amount, null, $this->admin, $note);
    }

    protected function user(string $key, string $first, string $last, UserRole $role, ?string $phone, string $password): User
    {
        // เบอร์ซ้ำกับคนจริงไม่ได้ ไม่งั้นการค้นหาเพื่อนด้วยเบอร์จะเจอผิดคน
        if ($phone && User::where('phone', $phone)->exists()) {
            $phone = null;
        }

        return User::create([
            'branch_id' => $this->branch->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $key.'@'.self::DOMAIN,
            'password' => $password,
            'role' => $role,
            'phone' => $phone,
            'email_verified_at' => now(),
        ])->refresh();
    }

    protected function trainer(string $key, string $name, TrainerType $type, string $password, array $extra = []): Trainer
    {
        $user = $this->user($key, $name, '(ตัวอย่าง)', UserRole::Trainer, null, $password);

        $trainer = Trainer::create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $this->branch->id,
            'type' => $type,
            'status' => TrainerStatus::Approved,
            'approved_at' => now(),
            'bio' => 'Trainer ตัวอย่างสำหรับทดลองระบบ',
            'specialties' => ['Hybrid Training', 'Strength'],
        ], $extra));

        // ว่างตามเวลาเปิดของสาขาทุกช่วง ลูกเทรนจะได้เลือกได้ทุกเวลาที่ยิมเปิด
        foreach ($this->branch->scheduleTemplates()->active()->get() as $template) {
            $trainer->availabilities()->create([
                'day_of_week' => $template->day_of_week,
                'start_time' => $template->start_time,
                'end_time' => $template->end_time,
            ]);
        }

        return $trainer->load('user');
    }

    protected function member(string $key, string $name, string $phone, string $password, ?Trainer $trainer): Member
    {
        $user = $this->user($key, $name, '(ตัวอย่าง)', UserRole::Member, $phone, $password);

        $member = Member::create([
            'user_id' => $user->id,
            'branch_id' => $this->branch->id,
            'primary_trainer_id' => $trainer?->id,
            'status' => MemberStatus::Active,
            'approved_at' => now(),
            'emergency_contact_name' => 'ผู้ติดต่อตัวอย่าง',
            'emergency_contact_phone' => '0990009999',
        ]);

        if ($trainer) {
            TeamMember::create([
                'trainer_id' => $trainer->id,
                'member_id' => $member->id,
                'status' => 'active',
                'joined_at' => now(),
                'joined_via' => 'admin',
            ]);
        }

        return $member->load('user');
    }

    /**
     * ลบข้อมูลตัวอย่างทั้งหมด
     * ถ้ามีคนจริงจองโดยเลือก Trainer ตัวอย่างไว้และยังไม่ถึงเวลา จะไม่ลบ เพราะงานนั้นจะหาย Trainer ไปเฉย ๆ
     *
     * @return array{users: int, reservations: int}
     */
    public function remove(): array
    {
        $users = User::withTrashed()->where('email', 'like', '%@'.self::DOMAIN)->get();
        $memberIds = Member::withTrashed()->whereIn('user_id', $users->pluck('id'))->pluck('id');
        $trainerIds = Trainer::withTrashed()->whereIn('user_id', $users->pluck('id'))->pluck('id');

        $blocking = Reservation::query()
            ->whereIn('trainer_id', $trainerIds)
            ->whereNotIn('payer_member_id', $memberIds)
            ->whereIn('status', ReservationStatus::holding())
            ->where('ends_at', '>', now())
            ->pluck('reference');

        if ($blocking->isNotEmpty()) {
            throw new \RuntimeException('มีลูกค้าจริงจอง Trainer ตัวอย่างไว้: '.$blocking->implode(', ').' เปลี่ยน Trainer ในหลังบ้านก่อนแล้วค่อยลบ');
        }

        return DB::transaction(function () use ($users, $memberIds, $trainerIds) {
            $reservationIds = Reservation::query()
                ->whereIn('payer_member_id', $memberIds)
                ->orWhereHas('participants', fn ($q) => $q->whereIn('members.id', $memberIds))
                ->pluck('id');

            AuditLog::where('subject_type', (new Reservation)->getMorphClass())->whereIn('subject_id', $reservationIds)->delete();
            AuditLog::where('subject_type', (new Member)->getMorphClass())->whereIn('subject_id', $memberIds)->delete();
            // งานในอดีตของคนจริงที่เคยใช้ Trainer ตัวอย่างยังอยู่ แค่ไม่มีชื่อ Trainer
            Reservation::whereIn('id', $reservationIds)->delete();

            DB::table('notifications')
                ->where('notifiable_type', (new User)->getMorphClass())
                ->whereIn('notifiable_id', $users->pluck('id'))
                ->delete();

            Member::withTrashed()->whereIn('id', $memberIds)->forceDelete();
            Trainer::withTrashed()->whereIn('id', $trainerIds)->forceDelete();
            User::withTrashed()->whereIn('id', $users->pluck('id'))->forceDelete();

            return ['users' => $users->count(), 'reservations' => $reservationIds->count()];
        });
    }
}
