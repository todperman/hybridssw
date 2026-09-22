<?php

namespace App\Livewire\Trainer;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Member;
use App\Models\Trainer;
use App\Models\WorkoutSession;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * หน้าจองหลักของเทรนเนอร์ เลือกวัน เลือกรอบ แล้วใส่ลูกทีมลงไป
 */
class BookingBoard extends Component
{
    public string $date;

    /** ไทม์ไลน์รายวัน หรือ ตารางทั้งสัปดาห์ จำค่าไว้ข้ามหน้าเพราะเป็นความชอบส่วนตัวของผู้ใช้ */
    #[Session(key: 'trainer-schedule-view')]
    public string $view = 'timeline';

    public const VIEWS = ['timeline' => 'ไทม์ไลน์', 'grid' => 'ตารางสัปดาห์'];

    /** ชื่อวันเริ่มที่จันทร์ ย่อไว้ใช้บนมือถือ เต็มไว้ใช้บนจอใหญ่ */
    public const DAY_SHORT = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];

    public const DAY_FULL = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัส', 'ศุกร์', 'เสาร์', 'อาทิตย์'];

    /**
     * แสดงเฉพาะรอบที่มีลูกทีมของเราอยู่
     * ตั้งใจไม่จำค่าข้ามหน้า เพราะตัวกรองที่ซ่อนข้อมูลไว้ข้ามวันจะทำให้สับสนว่าทำไมตารางว่าง
     */
    public bool $onlyMine = false;

    /** รอบที่กำลังเปิด modal จองอยู่ */
    public ?int $selectedSessionId = null;

    /** id ลูกทีมที่ติ๊กไว้ในกล่องจอง */
    public array $selectedMemberIds = [];

    public bool $allowWaitlist = true;

    /** ผลลัพธ์รายคนหลังกดจอง เพื่อบอกว่าใครติดเงื่อนไขอะไร */
    public array $lastResult = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function trainer(): Trainer
    {
        return auth()->user()->trainer->loadMissing('branch');
    }

    #[Computed]
    public function sessions(): Collection
    {
        return WorkoutSession::query()
            ->where('branch_id', $this->trainer()->branch_id)
            ->whereDate('date', $this->date)
            ->when($this->onlyMine, fn ($q) => $q->whereHas('bookings', fn ($b) => $b
                ->where('trainer_id', $this->trainer()->id)
                ->whereIn('status', $this->seatStatuses())))
            ->with(['activeBookings.member.user', 'activeBookings.trainer.user', 'claimedByTrainer.user'])
            ->orderBy('starts_at')
            ->get();
    }

    #[Computed]
    public function team(): Collection
    {
        return $this->trainer()
            ->teamMembers()
            ->with('user')
            ->orderBy('users.name')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->select('members.*')
            ->get();
    }

    /** กลุ่มลูกทีมที่เปิดใช้งานอยู่ ใช้เลือกทั้งกลุ่มในกล่องจอง */
    #[Computed]
    public function groups(): Collection
    {
        return $this->trainer()
            ->memberGroups()
            ->active()
            ->with('members.user')
            ->withCount('members')
            ->having('members_count', '>', 0)
            ->orderBy('name')
            ->get();
    }

    /**
     * ติ๊กสมาชิกทั้งกลุ่มให้ในคลิกเดียว
     * ข้ามคนที่จองรอบนี้ไปแล้ว และไม่เกินจำนวนที่นั่งที่เหลือเพื่อไม่ให้เผลอดันเข้าคิวสำรองทั้งกลุ่ม
     */
    public function applyGroup(int $groupId): void
    {
        $group = $this->groups->firstWhere('id', $groupId);
        $session = $this->selectedSession;

        if (! $group || ! $session) {
            return;
        }

        $booked = $this->alreadyBookedMemberIds;
        $available = $group->members->pluck('id')->reject(fn ($id) => in_array($id, $booked, true))->values();

        $seats = $session->seatsRemaining();
        $picked = $this->allowWaitlist ? $available : $available->take($seats);

        $this->selectedMemberIds = $picked->map(fn ($id) => (string) $id)->all();

        $skipped = $available->count() - $picked->count();

        $this->dispatch('toast',
            tone: $skipped > 0 ? 'warning' : 'info',
            title: 'เลือก '.$picked->count().' คนจากกลุ่ม '.$group->name,
            body: $skipped > 0
                ? "ที่นั่งเหลือ {$seats} จึงยังไม่เลือกอีก {$skipped} คน เปิดคิวสำรองถ้าต้องการทั้งกลุ่ม"
                : null,
        );
    }

    /**
     * ดึงรอบที่เลือกจากฐานข้อมูลตรงๆ ไม่ใช่จากรายการของวันที่กำลังดู
     * เพราะในมุมมองตารางสัปดาห์ ผู้ใช้กดช่องของวันอื่นได้ ซึ่งไม่อยู่ในรายการนั้น
     */
    #[Computed]
    public function selectedSession(): ?WorkoutSession
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        return WorkoutSession::query()
            ->where('branch_id', $this->trainer()->branch_id)
            ->with(['activeBookings.member.user', 'activeBookings.trainer', 'claimedByTrainer', 'branch'])
            ->find($this->selectedSessionId);
    }

    /** ลูกทีมที่ยังจองรอบนี้ไม่ได้เพราะจองไปแล้ว จะถูกปิดไม่ให้ติ๊ก */
    #[Computed]
    public function alreadyBookedMemberIds(): array
    {
        if (! $this->selectedSession) {
            return [];
        }

        return Booking::where('workout_session_id', $this->selectedSessionId)
            ->whereNotNull('active_member_key')
            ->pluck('member_id')
            ->all();
    }

    /**
     * แถบเลือกวันแบบสัปดาห์ พร้อมจำนวนที่นั่งว่างของแต่ละวัน
     * ดึงยอดทั้งสัปดาห์ในคิวรีเดียว ไม่วนคิวรีทีละวัน
     *
     * @return array<int, array{date:string, label:string, day:int, isToday:bool, isSelected:bool, open:int, capacity:int}>
     */
    #[Computed]
    public function weekDays(): array
    {
        $start = CarbonImmutable::parse($this->date)->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $end = $start->addDays(6);

        $totals = WorkoutSession::query()
            ->where('branch_id', $this->trainer()->branch_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', \App\Enums\SessionStatus::Open->value)
            ->selectRaw('date, SUM(capacity) as capacity, SUM(booked_count) as booked')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => CarbonImmutable::parse($row->date)->toDateString());

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->addDays($i);
            $key = $day->toDateString();
            $row = $totals->get($key);

            $days[] = [
                'date' => $key,
                'label' => self::DAY_SHORT[$i],
                'labelFull' => self::DAY_FULL[$i],
                'day' => (int) $day->format('j'),
                'isToday' => $day->isSameDay(now()),
                'isSelected' => $key === $this->date,
                'isPast' => $day->lt(now()->startOfDay()),
                'open' => max(0, (int) ($row->capacity ?? 0) - (int) ($row->booked ?? 0)),
                'capacity' => (int) ($row->capacity ?? 0),
            ];
        }

        return $days;
    }

    /** ยอดรวมของวันที่เลือก ใช้โชว์บนหัวหน้า */
    #[Computed]
    public function daySummary(): array
    {
        $sessions = $this->sessions;
        $trainerId = $this->trainer()->id;

        return [
            'sessions' => $sessions->count(),
            'openSeats' => $sessions->where('status', \App\Enums\SessionStatus::Open)->sum(fn (WorkoutSession $s) => $s->seatsRemaining()),
            'mine' => $sessions->sum(fn (WorkoutSession $s) => $s->activeBookings->where('trainer_id', $trainerId)->count()),
        ];
    }

    /**
     * จัดรอบเป็นช่วงเช้า/บ่าย/เย็น เพื่อให้กวาดตาหาช่วงที่ต้องการได้เร็วกว่าไล่ดูทีละรอบ
     *
     * @return array<string, array{label:string, hint:string, sessions:array<int, WorkoutSession>, openSeats:int}>
     */
    #[Computed]
    public function groupedSessions(): array
    {
        $groups = [
            'morning' => ['label' => 'ช่วงเช้า', 'hint' => 'ก่อนเที่ยง', 'sessions' => [], 'openSeats' => 0],
            'afternoon' => ['label' => 'ช่วงบ่าย', 'hint' => '12:00 - 16:59', 'sessions' => [], 'openSeats' => 0],
            'evening' => ['label' => 'ช่วงเย็น', 'hint' => 'ตั้งแต่ 17:00', 'sessions' => [], 'openSeats' => 0],
        ];

        foreach ($this->sessions as $session) {
            $hour = (int) $session->starts_at->format('G');
            $key = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

            $groups[$key]['sessions'][] = $session;

            if ($session->status->acceptsBookings()) {
                $groups[$key]['openSeats'] += $session->seatsRemaining();
            }
        }

        return array_filter($groups, fn (array $g) => $g['sessions'] !== []);
    }

    /**
     * รอบถัดไปที่เทรนเนอร์คนนี้มีลูกทีมรออยู่ ไม่จำกัดเฉพาะวันที่เลือกดู
     * เพื่อให้เปิดหน้ามาแล้วรู้ทันทีว่าต้องไปคุมรอบไหนต่อ
     */
    #[Computed]
    public function nextSession(): ?WorkoutSession
    {
        return WorkoutSession::query()
            ->where('branch_id', $this->trainer()->branch_id)
            ->where('starts_at', '>=', now())
            ->whereHas('bookings', fn ($q) => $q
                ->where('trainer_id', $this->trainer()->id)
                ->whereIn('status', [BookingStatus::Booked->value, BookingStatus::CheckedIn->value]))
            ->withCount(['bookings as my_bookings_count' => fn ($q) => $q
                ->where('trainer_id', $this->trainer()->id)
                ->whereIn('status', [BookingStatus::Booked->value, BookingStatus::CheckedIn->value])])
            ->orderBy('starts_at')
            ->first();
    }

    /** สถานะที่ถือว่ายังกินที่นั่งอยู่ ใช้ร่วมกันหลายคิวรี */
    protected function seatStatuses(): array
    {
        return [
            BookingStatus::Booked->value,
            BookingStatus::CheckedIn->value,
            BookingStatus::Completed->value,
        ];
    }

    public function toggleOnlyMine(): void
    {
        $this->onlyMine = ! $this->onlyMine;
        $this->reset('selectedSessionId', 'selectedMemberIds', 'lastResult');
        $this->refreshData();
    }

    public function setView(string $view): void
    {
        $this->view = array_key_exists($view, self::VIEWS) ? $view : 'timeline';
        $this->reset('selectedSessionId', 'selectedMemberIds', 'lastResult');
    }

    /**
     * ตารางทั้งสัปดาห์ แถวคือชั่วโมง คอลัมน์คือวันที่
     * ดึงทั้งสัปดาห์ในคิวรีเดียว ไม่วนคิวรีทีละวัน
     *
     * @return array{days: array<int, array{date:string, label:string, day:int, isToday:bool}>, hours: array<int,int>, cells: array<int, array<string, WorkoutSession>>}
     */
    #[Computed]
    public function weekGrid(): array
    {
        $start = CarbonImmutable::parse($this->date)->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->addDays($i);
            $days[] = [
                'date' => $d->toDateString(),
                'label' => self::DAY_SHORT[$i],
                'labelFull' => self::DAY_FULL[$i],
                'day' => (int) $d->format('j'),
                'isToday' => $d->isSameDay(now()),
            ];
        }

        $sessions = WorkoutSession::query()
            ->where('branch_id', $this->trainer()->branch_id)
            ->whereBetween('date', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->when($this->onlyMine, fn ($q) => $q->whereHas('bookings', fn ($b) => $b
                ->where('trainer_id', $this->trainer()->id)
                ->whereIn('status', $this->seatStatuses())))
            ->with(['activeBookings', 'claimedByTrainer'])
            ->orderBy('starts_at')
            ->get();

        $cells = [];
        $hours = [];

        foreach ($sessions as $session) {
            $hr = (int) $session->starts_at->format('G');
            $hours[$hr] = true;
            $cells[$hr][$session->date->toDateString()] = $session;
        }

        ksort($hours);

        return ['days' => $days, 'hours' => array_keys($hours), 'cells' => $cells];
    }

    public function selectDate(string $date): void
    {
        $this->date = $date;
        $this->reset('selectedSessionId', 'selectedMemberIds', 'lastResult');
        $this->refreshData();
    }

    public function shiftWeek(int $weeks): void
    {
        $this->selectDate(CarbonImmutable::parse($this->date)->addWeeks($weeks)->toDateString());
    }

    public function goToToday(): void
    {
        $this->selectDate(now()->toDateString());
    }

    public function shiftDate(int $days): void
    {
        $this->date = CarbonImmutable::parse($this->date)->addDays($days)->toDateString();
        $this->reset('selectedSessionId', 'selectedMemberIds', 'lastResult');
        $this->refreshData();
    }

    public function openSession(int $sessionId): void
    {
        $this->selectedSessionId = $sessionId;
        $this->selectedMemberIds = [];
        $this->lastResult = [];
    }

    public function closeSession(): void
    {
        $this->reset('selectedSessionId', 'selectedMemberIds');
    }

    public function book(BookingService $bookings): void
    {
        $session = $this->selectedSession;

        if (! $session || $this->selectedMemberIds === []) {
            return;
        }

        $members = Member::whereIn('id', $this->selectedMemberIds)->with('user')->get()->all();

        $result = $bookings->bookMany(
            $session,
            $members,
            $this->trainer(),
            auth()->user(),
            $this->allowWaitlist,
        );

        $this->lastResult = [
            'booked' => collect($result['booked'])->map(fn (Booking $b) => [
                'name' => $b->member->user->name,
                'status' => $b->status->label(),
                'waitlisted' => $b->status === BookingStatus::Waitlisted,
            ])->all(),
            'failed' => collect($result['failed'])->map(fn (array $f) => [
                'name' => $f['member']->user->name,
                'reason' => $f['reason'],
            ])->all(),
        ];

        $this->selectedMemberIds = [];
        $this->refreshData();
        $this->announceBookingResult();
    }

    /** สรุปผลการจองเป็น toast เพื่อให้เห็นผลแม้จะเลื่อนหน้าไปแล้ว */
    protected function announceBookingResult(): void
    {
        $ok = count($this->lastResult['booked'] ?? []);
        $failed = count($this->lastResult['failed'] ?? []);
        $waitlisted = collect($this->lastResult['booked'] ?? [])->where('waitlisted', true)->count();

        if ($ok === 0) {
            $this->dispatch('toast',
                tone: 'error',
                title: 'จองไม่สำเร็จ',
                body: $failed === 1
                    ? ($this->lastResult['failed'][0]['reason'] ?? '')
                    : "ติดเงื่อนไข {$failed} รายการ ดูรายละเอียดในกล่อง",
            );

            return;
        }

        $seated = $ok - $waitlisted;
        $parts = [];

        if ($seated > 0) {
            $parts[] = "ได้ที่นั่ง {$seated} คน";
        }

        if ($waitlisted > 0) {
            $parts[] = "เข้าคิวสำรอง {$waitlisted} คน";
        }

        if ($failed > 0) {
            $parts[] = "ไม่สำเร็จ {$failed} คน";
        }

        $this->dispatch('toast',
            tone: $failed > 0 ? 'warning' : 'success',
            title: 'บันทึกการจองแล้ว',
            body: implode(' · ', $parts),
        );
    }

    public function cancelBooking(int $bookingId, BookingService $bookings): void
    {
        $booking = Booking::with('workoutSession')->findOrFail($bookingId);

        // เทรนเนอร์ยกเลิกได้เฉพาะการจองที่ตัวเองเป็นคนทำ
        abort_unless($booking->trainer_id === $this->trainer()->id, 403);

        try {
            $bookings->cancel($booking, auth()->user(), 'เทรนเนอร์ยกเลิก');

            $this->dispatch('toast',
                tone: 'success',
                title: 'ยกเลิกการจองแล้ว',
                body: $booking->fresh()->cancelled_late
                    ? 'เลยกำหนดยกเลิกฟรี จึงถูกหักเครดิต'
                    : 'คืนเครดิตให้ลูกทีมเรียบร้อย',
            );
        } catch (BookingException $e) {
            $this->dispatch('toast', tone: 'error', title: 'ยกเลิกไม่ได้', body: $e->getMessage());
        }

        $this->refreshData();
    }

    /** ล้าง computed ทุกตัวที่อิงจำนวนที่นั่ง ไม่งั้นบางส่วนของหน้าจะโชว์ค่าก่อนจอง */
    protected function refreshData(): void
    {
        unset(
            $this->sessions,
            $this->selectedSession,
            $this->alreadyBookedMemberIds,
            $this->weekDays,
            $this->daySummary,
            $this->groupedSessions,
            $this->nextSession,
            $this->groups,
            $this->weekGrid,
        );
    }

    public function render()
    {
        return view('livewire.trainer.booking-board')
            ->layout('layouts.app');
    }
}
