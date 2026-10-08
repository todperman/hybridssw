<?php

namespace App\Livewire\Reservations;

use App\Exceptions\ReservationException;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Services\Reservations\Availability;
use App\Services\Reservations\MemberLookup;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * หน้าจองยิม ใช้ทั้ง Trainee และ Trainer
 *
 * Trainee: ผู้จองอยู่ในกลุ่มเสมอ เลือก Trainer ที่ว่างครบช่วงเอง
 * Trainer: เพิ่มลูกเทรน 1–6 คน ระบบกำหนดผู้จองเป็น Trainer ประจำการจอง
 * ทั้งสองแบบต้องเลือกผู้ชำระเงินหนึ่งคนจากผู้เข้าร่วม
 *
 * หน้านี้แค่ช่วยเลือก ตัดสินจริงทุกอย่างที่ ReservationService
 */
class BookingWizard extends Component
{
    /** trainee | trainer มาจากเส้นทางที่เปิด ห้ามเปลี่ยนจากหน้าเว็บ */
    #[Locked]
    public string $mode = 'trainee';

    public string $date = '';
    public int $hours = 1;
    public ?string $start = null;

    /** @var array<int, int> */
    public array $memberIds = [];
    public string $lookup = '';
    public ?string $lookupError = null;

    public ?int $trainerId = null;
    public bool $noTrainer = false;
    public ?int $payerId = null;

    public ?string $error = null;

    public function mount(): void
    {
        // middleware ของแต่ละกลุ่มเส้นทางกันสิทธิ์และสถานะอนุมัติไว้แล้ว
        $user = auth()->user();
        $this->mode = self::modeFor(auth()->user());

        $this->date = now()->toDateString();

        if ($this->isTrainee()) {
            $this->memberIds = [$user->member->id];
            $this->payerId = $user->member->id;
        } else {
            $this->trainerId = $user->trainer->id;
        }
    }

    public function isTrainee(): bool
    {
        return $this->mode === 'trainee';
    }

    #[Computed]
    public function branch(): Branch
    {
        $user = auth()->user();

        return $this->isTrainee() ? $user->member->branch : $user->trainer->branch;
    }

    /** @return Collection<int, CarbonImmutable> */
    #[Computed]
    public function days(): Collection
    {
        $today = CarbonImmutable::today();

        return collect(range(0, (int) $this->branch->booking_window_days))
            ->map(fn (int $i) => $today->addDays($i));
    }

    /** @return array<int, CarbonImmutable> */
    #[Computed]
    public function startTimes(): array
    {
        $times = app(Availability::class)->gymStartTimes($this->branch, CarbonImmutable::parse($this->date), $this->hours);

        // Trainer จองให้ลูกเทรน ต้องแสดงเฉพาะเวลาที่ตัวเองว่างด้วย
        if (! $this->isTrainee()) {
            $me = auth()->user()->trainer;
            $times = array_values(array_filter($times, fn ($t) => app(Availability::class)->trainerCovers($me, $t, $this->hours)));
        }

        return $times;
    }

    #[Computed]
    public function startAt(): ?CarbonImmutable
    {
        return $this->start ? CarbonImmutable::parse($this->date.' '.$this->start) : null;
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function participants(): Collection
    {
        $members = Member::with('user')->whereKey($this->memberIds)->get()->keyBy('id');

        return collect($this->memberIds)->map(fn ($id) => $members->get($id))->filter()->values();
    }

    /** Trainer ที่ว่างครบทุกชั่วโมงของช่วงที่เลือก */
    #[Computed]
    public function trainers(): Collection
    {
        if (! $this->startAt) {
            return collect();
        }

        return app(Availability::class)->availableTrainers($this->branch, $this->startAt, $this->hours);
    }

    #[Computed]
    public function mayGoWithoutTrainer(): bool
    {
        return $this->participants->isNotEmpty()
            && app(ReservationService::class)->mayGoWithoutTrainer($this->participants);
    }

    /** ลูกทีมของ Trainer ที่ยังไม่อยู่ในกลุ่ม แตะเพิ่มได้เลยไม่ต้องพิมพ์รหัส */
    #[Computed]
    public function teamShortcuts(): Collection
    {
        if ($this->isTrainee()) {
            return collect();
        }

        return auth()->user()->trainer->teamMembers()->with('user')->get()
            ->reject(fn (Member $m) => in_array($m->id, $this->memberIds, true) || ! $m->canJoinReservations())
            ->values();
    }

    /** กลุ่มลูกทีมที่ Trainer สร้างไว้ แตะครั้งเดียวเพิ่มทั้งกลุ่ม */
    #[Computed]
    public function groups(): Collection
    {
        if ($this->isTrainee()) {
            return collect();
        }

        return auth()->user()->trainer->memberGroups()->active()->with('members')->orderBy('name')->get()
            ->filter(fn ($g) => $g->members->isNotEmpty())
            ->values();
    }

    #[Computed]
    public function amount(): string
    {
        return bcmul((string) $this->branch->hourly_rate, (string) $this->hours, 2);
    }

    // --- การเลือก ---

    public function selectDay(string $date): void
    {
        $this->date = $date;
        $this->resetTime();
    }

    public function setHours(int $hours): void
    {
        $this->hours = max(1, min($hours, (int) $this->branch->max_booking_hours));
        $this->resetTime();
    }

    public function pickStart(string $time): void
    {
        $this->start = $time;
        $this->error = null;

        if ($this->isTrainee()) {
            $this->trainerId = null;
        }

        unset($this->trainers);
    }

    public function chooseTrainer(?int $id): void
    {
        // ไม่มีสิทธิ์ก็เลือกเข้าใช้เองไม่ได้ ต่อให้ส่งค่ามาเองจากหน้าเว็บ
        if ($id === null && ! $this->mayGoWithoutTrainer) {
            return;
        }

        $this->trainerId = $id;
        $this->noTrainer = $id === null;
    }

    public function addMember(MemberLookup $lookup): void
    {
        $this->lookupError = null;
        $max = (int) $this->branch->max_trainees;

        if (count($this->memberIds) >= $max) {
            $this->lookupError = "กลุ่มเต็มแล้ว ({$max} คน)";

            return;
        }

        $member = $lookup->find($this->branch, $this->lookup);

        if (! $member) {
            $this->lookupError = 'ไม่พบสมาชิกที่ใช้รหัสหรือเบอร์นี้ หรือยังเพิ่มในการจองไม่ได้';

            return;
        }

        $this->pushMember($member);
        $this->lookup = '';
    }

    public function addFromTeam(int $memberId): void
    {
        $member = $this->teamShortcuts->firstWhere('id', $memberId);

        if ($member && count($this->memberIds) < (int) $this->branch->max_trainees) {
            $this->pushMember($member);
        }
    }

    /**
     * เพิ่มทุกคนในกลุ่มที่ยังไม่อยู่ในการจอง ข้ามคนที่ยังเพิ่มไม่ได้
     * ถ้าเกินจำนวนต่อการจอง เพิ่มเท่าที่ใส่ได้แล้วบอกว่าเหลือกี่คน
     */
    public function addGroup(int $groupId): void
    {
        $this->lookupError = null;
        $group = $this->groups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        $max = (int) $this->branch->max_trainees;
        $candidates = $group->members
            ->reject(fn (Member $m) => in_array($m->id, $this->memberIds, true))
            ->filter(fn (Member $m) => $m->branch_id === $this->branch->id && $m->canJoinReservations())
            ->values();

        $room = $max - count($this->memberIds);
        $candidates->take(max(0, $room))->each(fn (Member $m) => $this->pushMember($m));

        if ($candidates->count() > $room) {
            $this->lookupError = 'เพิ่มได้ '.max(0, $room).' คน กลุ่มนี้มีคนเกินจำนวนต่อการจอง ('.$max.' คน)';
        }
    }

    public function removeMember(int $memberId): void
    {
        // Trainee เอาตัวเองออกจากกลุ่มไม่ได้ ผู้จองต้องอยู่ในการจองเสมอ
        if ($this->isTrainee() && $memberId === auth()->user()->member->id) {
            return;
        }

        $this->memberIds = array_values(array_diff($this->memberIds, [$memberId]));

        if ($this->payerId === $memberId) {
            $this->payerId = $this->memberIds[0] ?? null;
        }

        $this->afterGroupChanged();
    }

    public function choosePayer(int $memberId): void
    {
        if (in_array($memberId, $this->memberIds, true)) {
            $this->payerId = $memberId;
        }
    }

    // --- ยืนยัน ---

    public function submit(ReservationService $reservations): void
    {
        $this->error = null;

        if (! $this->startAt) {
            $this->error = 'เลือกเวลาเริ่มก่อน';

            return;
        }

        if (! $this->payerId) {
            $this->error = 'เลือกผู้ชำระเงินก่อน';

            return;
        }

        $trainer = $this->trainerId ? Trainer::find($this->trainerId) : null;

        try {
            $reservation = $reservations->create(
                $this->branch,
                $this->startAt,
                $this->hours,
                $this->memberIds,
                $this->payerId,
                $trainer,
                auth()->user(),
                $this->isTrainee() ? Reservation::VIA_TRAINEE : Reservation::VIA_TRAINER,
            );
        } catch (ReservationException $e) {
            $this->error = $e->getMessage();
            unset($this->startTimes, $this->trainers);

            return;
        }

        $this->redirectRoute('reservations.show', $reservation->reference, navigate: true);
    }

    protected function pushMember(Member $member): void
    {
        if (! in_array($member->id, $this->memberIds, true)) {
            $this->memberIds[] = $member->id;
        }

        $this->payerId ??= $member->id;
        $this->afterGroupChanged();
    }

    protected function afterGroupChanged(): void
    {
        unset($this->participants, $this->mayGoWithoutTrainer, $this->teamShortcuts, $this->groups);

        // กลุ่มเปลี่ยนแล้วไม่มีสิทธิ์ไปแบบไม่มี Trainer แล้ว ต้องกลับมาเลือก Trainer
        if ($this->noTrainer && ! $this->mayGoWithoutTrainer) {
            $this->noTrainer = false;
        }
    }

    protected function resetTime(): void
    {
        $this->start = null;
        $this->error = null;

        // เปลี่ยนวันหรือชั่วโมงแล้ว Trainer ที่เลือกไว้อาจไม่ว่าง ต้องเลือกใหม่
        // แต่การเลือกเข้าใช้เองไม่ขึ้นกับเวลา จึงคงไว้
        if ($this->isTrainee()) {
            $this->trainerId = null;
        }

        unset($this->startTimes, $this->trainers, $this->startAt, $this->amount);
    }

    /** ใช้เส้นทางที่เปิดก่อน ถ้าไม่มี (เช่นถูกฝังในหน้าอื่น) ดูจากโปรไฟล์ที่ผู้ใช้มี */
    public static function modeFor(\App\Models\User $user): string
    {
        $mode = match (true) {
            request()->routeIs('trainer.*') => 'trainer',
            request()->routeIs('member.*') => 'trainee',
            default => $user->member ? 'trainee' : 'trainer',
        };

        abort_if($mode === 'trainer' ? $user->trainer === null : $user->member === null, 403);

        return $mode;
    }

    public function render()
    {
        return view('livewire.reservations.booking-wizard')->layout('layouts.app');
    }
}
