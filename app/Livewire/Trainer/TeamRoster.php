<?php

namespace App\Livewire\Trainer;

use App\Exceptions\MemberGroupException;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\TeamMember;
use App\Models\Trainer;
use App\Models\User;
use App\Rules\ThaiPhone;
use App\Services\AvatarService;
use App\Support\GroupPalette;
use App\Services\MemberGroupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class TeamRoster extends Component
{
    use WithFileUploads;

    public string $search = '';

    /** รูปที่กำลังอัปโหลด แยกตาม id ของลูกทีม */
    public array $photos = [];

    // ฟอร์มสร้าง/แก้ไขกลุ่ม
    public bool $showGroupForm = false;
    public ?int $editingGroupId = null;
    public string $groupName = '';
    public string $groupDescription = '';
    public string $groupColor = GroupPalette::DEFAULT;

    /** กลุ่มที่กำลังเปิดแผงจัดสมาชิกอยู่ */
    public ?int $managingGroupId = null;

    public function trainer(): Trainer
    {
        return auth()->user()->trainer;
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->trainer()
            ->teamMembers()
            ->with(['user', 'packages', 'groups'])
            ->when($this->search !== '', fn ($q) => $q->whereHas(
                'user',
                fn ($u) => $u->where('name', 'like', "%{$this->search}%")
                    ->orWhere('nickname', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->get();
    }

    #[Computed]
    public function groups(): Collection
    {
        return $this->trainer()
            ->memberGroups()
            ->with(['members.user', 'branch'])
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function managingGroup(): ?MemberGroup
    {
        return $this->managingGroupId
            ? $this->groups->firstWhere('id', $this->managingGroupId)
            : null;
    }

    /** ลูกทีมที่ยังไม่อยู่ในกลุ่มที่กำลังจัดอยู่ */
    #[Computed]
    public function assignableMembers(): Collection
    {
        $group = $this->managingGroup;

        if (! $group) {
            return collect();
        }

        $inGroup = $group->members->pluck('id')->all();

        return $this->trainer()
            ->teamMembers()
            ->with('user')
            ->get()
            ->reject(fn (Member $m) => in_array($m->id, $inGroup, true))
            ->values();
    }

    // --- กลุ่ม ---

    public function openGroupForm(?int $groupId = null): void
    {
        $this->editingGroupId = $groupId;

        $group = $groupId ? $this->groups->firstWhere('id', $groupId) : null;

        $this->groupName = $group->name ?? '';
        $this->groupDescription = $group->description ?? '';
        $this->groupColor = $group->color ?? GroupPalette::DEFAULT;
        $this->showGroupForm = true;
        $this->resetErrorBag();
    }

    public function closeGroupForm(): void
    {
        $this->reset('showGroupForm', 'editingGroupId', 'groupName', 'groupDescription', 'groupColor');
        $this->resetErrorBag();
    }

    public function saveGroup(MemberGroupService $service): void
    {
        $this->validate([
            'groupName' => ['required', 'string', 'max:80'],
            'groupDescription' => ['nullable', 'string', 'max:255'],
            'groupColor' => ['required', GroupPalette::rule()],
        ], attributes: [
            'groupName' => 'ชื่อกลุ่ม',
            'groupDescription' => 'คำอธิบาย',
            'groupColor' => 'สีประจำกลุ่ม',
        ]);

        try {
            if ($this->editingGroupId) {
                $group = $this->groups->firstWhere('id', $this->editingGroupId);
                $service->rename($group, $this->trainer(), $this->groupName, $this->groupDescription, $this->groupColor);
                $title = 'แก้ไขกลุ่มแล้ว';
            } else {
                $service->createGroup($this->trainer(), $this->groupName, $this->groupDescription, $this->groupColor);
                $title = 'สร้างกลุ่มแล้ว';
            }
        } catch (MemberGroupException $e) {
            $this->addError('groupName', $e->getMessage());

            return;
        }

        $this->closeGroupForm();
        $this->refreshGroups();
        $this->dispatch('toast', tone: 'success', title: $title, body: $this->groupName);
    }

    public function deleteGroup(int $groupId, MemberGroupService $service): void
    {
        $group = $this->groups->firstWhere('id', $groupId);

        if (! $group) {
            return;
        }

        try {
            $service->deleteGroup($group, $this->trainer());
        } catch (MemberGroupException $e) {
            $this->dispatch('toast', tone: 'error', title: 'ลบกลุ่มไม่ได้', body: $e->getMessage());

            return;
        }

        if ($this->managingGroupId === $groupId) {
            $this->managingGroupId = null;
        }

        $this->refreshGroups();
        $this->dispatch('toast', tone: 'success', title: 'ลบกลุ่มแล้ว', body: 'ลูกทีมยังอยู่ในทีมตามเดิม');
    }

    public function setGroupColor(string $key): void
    {
        $this->groupColor = in_array($key, GroupPalette::keys(), true) ? $key : GroupPalette::DEFAULT;
    }

    public function manageGroup(?int $groupId): void
    {
        $this->managingGroupId = $groupId;
        unset($this->assignableMembers);
    }

    public function addToGroup(int $memberId, MemberGroupService $service): void
    {
        $group = $this->managingGroup;
        $member = Member::with('user')->find($memberId);

        if (! $group || ! $member) {
            return;
        }

        try {
            $service->addMember($group, $member, $this->trainer());
        } catch (MemberGroupException $e) {
            $this->dispatch('toast', tone: 'error', title: 'เพิ่มเข้ากลุ่มไม่ได้', body: $e->getMessage());

            return;
        }

        $this->refreshGroups();
        $this->dispatch('toast', tone: 'success', title: 'เพิ่มเข้ากลุ่มแล้ว', body: $member->user->name);
    }

    public function removeFromGroup(int $memberId, MemberGroupService $service): void
    {
        $group = $this->managingGroup;
        $member = Member::with('user')->find($memberId);

        if (! $group || ! $member) {
            return;
        }

        $service->removeMember($group, $member, $this->trainer());

        $this->refreshGroups();
        $this->dispatch('toast', tone: 'success', title: 'นำออกจากกลุ่มแล้ว', body: $member->user->name);
    }

    /**
     * เทรนเนอร์ตั้งรูปให้ลูกทีมได้ เพราะหน้างานมักถ่ายรูปให้ตอนสมัคร
     * แต่ตั้งได้เฉพาะคนที่อยู่ในทีมตัวเองเท่านั้น
     */
    public function updatedPhotos($value, $key): void
    {
        $memberId = (int) $key;

        $this->validate(
            ["photos.$memberId" => ['image', 'max:4096']],
            attributes: ["photos.$memberId" => 'รูปโปรไฟล์'],
        );

        $member = $this->trainer()->teamMembers()->with('user')->find($memberId);

        if (! $member) {
            $this->dispatch('toast', tone: 'error', title: 'ตั้งรูปไม่ได้', body: 'ลูกทีมคนนี้ไม่ได้อยู่ในทีมของคุณ');

            return;
        }

        app(AvatarService::class)->store($member->user, $value);

        unset($this->photos[$memberId]);
        $this->refreshGroups();

        $this->dispatch('toast', tone: 'success', title: 'อัปเดตรูปแล้ว', body: $member->user->name);
    }

    // --- ทีม ---

    /* ---------- แก้ไขข้อมูลลูกทีม ---------- */

    public ?int $editingMemberId = null;

    public string $editFirstName = '';

    public string $editLastName = '';

    public string $editNickname = '';

    public string $editPhone = '';

    public string $editDob = '';

    public string $editGender = '';

    public string $editEmergencyName = '';

    public string $editEmergencyPhone = '';

    public string $editHealthNote = '';

    /** ต้องเป็นลูกทีมของเทรนเนอร์คนนี้เท่านั้น ไม่งั้นแก้ข้อมูลคนอื่นได้ */
    protected function ownedMember(int $memberId): Member
    {
        return $this->trainer()
            ->teamMembers()
            ->where('members.id', $memberId)
            ->firstOrFail();
    }

    public function editMember(int $memberId): void
    {
        $member = $this->ownedMember($memberId);

        $this->editingMemberId = $member->id;
        $this->editFirstName = $member->user->first_name;
        $this->editLastName = $member->user->last_name;
        $this->editNickname = $member->user->nickname ?? '';
        $this->editPhone = $member->user->phone ?? '';
        $this->editDob = $member->date_of_birth?->toDateString() ?? '';
        $this->editGender = $member->gender ?? '';
        $this->editEmergencyName = $member->emergency_contact_name ?? '';
        $this->editEmergencyPhone = $member->emergency_contact_phone ?? '';
        $this->editHealthNote = $member->health_note ?? '';

        $this->resetValidation();
    }

    public function closeEditForm(): void
    {
        $this->editingMemberId = null;
    }

    /**
     * เทรนเนอร์แก้ได้เฉพาะข้อมูลติดต่อและข้อมูลสุขภาพ
     * อีเมลกับรหัสผ่านเป็นตัวตนสำหรับเข้าระบบ จึงไม่เปิดให้แก้แทนกัน
     * ไม่งั้นเทรนเนอร์ยึดบัญชีลูกทีมได้ด้วยการเปลี่ยนอีเมลแล้วกดลืมรหัสผ่าน
     */
    public function updateMember(): void
    {
        $member = $this->ownedMember($this->editingMemberId);

        $data = $this->validate([
            'editFirstName' => ['required', 'string', 'max:120'],
            'editLastName' => ['required', 'string', 'max:120'],
            'editNickname' => ['nullable', 'string', 'max:60'],
            'editPhone' => ['nullable', 'string', 'max:30', new ThaiPhone],
            'editDob' => ['nullable', 'date', 'before:today'],
            'editGender' => ['nullable', 'in:male,female,other'],
            'editEmergencyName' => ['nullable', 'string', 'max:255'],
            'editEmergencyPhone' => ['nullable', 'string', 'max:30', new ThaiPhone],
            'editHealthNote' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'editFirstName' => 'ชื่อ',
            'editLastName' => 'นามสกุล',
            'editNickname' => 'ชื่อเล่น',
            'editPhone' => 'เบอร์โทร',
            'editDob' => 'วันเกิด',
            'editGender' => 'เพศ',
            'editEmergencyName' => 'ชื่อผู้ติดต่อฉุกเฉิน',
            'editEmergencyPhone' => 'เบอร์ผู้ติดต่อฉุกเฉิน',
            'editHealthNote' => 'โน้ตสุขภาพ',
        ]);

        DB::transaction(function () use ($member, $data) {
            $member->user->update([
                'first_name' => $data['editFirstName'],
                'last_name' => $data['editLastName'],
                'nickname' => $data['editNickname'] ?: null,
                'phone' => $data['editPhone'] ? ThaiPhone::digits($data['editPhone']) : null,
            ]);

            $member->update([
                'date_of_birth' => $data['editDob'] ?: null,
                'gender' => $data['editGender'] ?: null,
                'emergency_contact_name' => $data['editEmergencyName'] ?: null,
                'emergency_contact_phone' => $data['editEmergencyPhone'] ? ThaiPhone::digits($data['editEmergencyPhone']) : null,
                'health_note' => $data['editHealthNote'] ?: null,
            ]);
        });

        $this->editingMemberId = null;
        unset($this->members);

        $this->dispatch('toast', tone: 'success', title: 'บันทึกข้อมูลลูกทีมแล้ว');
    }

    /* ---------- เพิ่มลูกทีมเอง ---------- */

    public bool $showAddForm = false;

    public string $newFirstName = '';

    public string $newLastName = '';

    public string $newNickname = '';

    public string $newEmail = '';

    public string $newPhone = '';

    public function openAddForm(): void
    {
        $this->reset(['newFirstName', 'newLastName', 'newNickname', 'newEmail', 'newPhone']);
        $this->resetValidation();
        $this->showAddForm = true;
    }

    public function closeAddForm(): void
    {
        $this->showAddForm = false;
    }

    /**
     * เพิ่มลูกทีมหน้างานโดยไม่ต้องส่งลิงก์
     *
     * ตั้งใจไม่ตั้งรหัสผ่านให้ ลูกทีมต้องกด "ลืมรหัสผ่าน" ตั้งเองครั้งแรก
     * เทรนเนอร์จะได้ไม่ต้องรู้รหัสผ่านของลูกทีม
     *
     * และไม่เซ็น PAR-Q แทนกันเด็ดขาด สมาชิกที่เพิ่มทางนี้จึงยังจองไม่ได้
     * จนกว่าจะเซ็นเอง ซึ่ง BookingService ตรวจอยู่แล้ว
     */
    public function addMember(): void
    {
        $trainer = $this->trainer();

        abort_unless($trainer->isApproved(), 403);

        $data = $this->validate([
            'newFirstName' => ['required', 'string', 'max:120'],
            'newLastName' => ['required', 'string', 'max:120'],
            'newNickname' => ['nullable', 'string', 'max:60'],
            'newEmail' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'newPhone' => ['nullable', 'string', 'max:30', new ThaiPhone],
        ], attributes: [
            'newFirstName' => 'ชื่อ',
            'newLastName' => 'นามสกุล',
            'newNickname' => 'ชื่อเล่น',
            'newEmail' => 'อีเมล',
            'newPhone' => 'เบอร์โทร',
        ]);

        $limit = $trainer->maxTeamSize();

        if ($limit !== null && $trainer->teamMembers()->count() >= $limit) {
            $this->addError('newEmail', 'ทีมเต็มแล้ว เพิ่มลูกทีมไม่ได้');

            return;
        }

        DB::transaction(function () use ($trainer, $data) {
            $user = User::create([
                'branch_id' => $trainer->branch_id,
                'first_name' => $data['newFirstName'],
                'last_name' => $data['newLastName'],
                'nickname' => $data['newNickname'] ?: null,
                'email' => $data['newEmail'],
                'phone' => $data['newPhone'] ? ThaiPhone::digits($data['newPhone']) : null,
                'password' => Str::password(32),
                'role' => UserRole::Member,
            ]);

            $member = Member::create([
                'user_id' => $user->id,
                'branch_id' => $trainer->branch_id,
                'primary_trainer_id' => $trainer->id,
                'status' => MemberStatus::Active,
            ]);

            TeamMember::create([
                'trainer_id' => $trainer->id,
                'member_id' => $member->id,
                'status' => 'active',
                'joined_at' => now(),
                'joined_via' => 'manual',
            ]);
        });

        $this->showAddForm = false;
        unset($this->members);

        $this->dispatch('toast',
            tone: 'success',
            title: 'เพิ่มลูกทีมแล้ว',
            body: 'ลูกทีมต้องเซ็นแบบคัดกรองสุขภาพเองก่อนจึงจะจองได้',
        );
    }

    /**
     * โน้ตนี้เห็นเฉพาะเทรนเนอร์เจ้าของทีม เก็บที่แถว pivot ไม่ใช่ที่ตัวลูกทีม
     * ลูกทีมคนเดียวอยู่ได้หลายทีม โน้ตจึงต้องไม่รั่วข้ามทีม
     */
    public function saveNote(int $memberId, string $note): void
    {
        $note = trim($note);

        // ตรวจค่าที่ส่งมาโดยตรง ไม่ใช่ property ของคอมโพเนนต์
        // เพราะโน้ตถูกส่งมาเป็นอาร์กิวเมนต์จากช่องกรอกของแต่ละแถว
        \Illuminate\Support\Facades\Validator::make(
            ['note' => $note],
            ['note' => ['nullable', 'string', 'max:500']],
            attributes: ['note' => 'โน้ต'],
        )->validate();

        $updated = TeamMember::where('trainer_id', $this->trainer()->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->update(['trainer_note' => $note ?: null]);

        if ($updated === 0) {
            return;
        }

        unset($this->members);

        $this->dispatch('toast', tone: 'success', title: 'บันทึกโน้ตแล้ว');
    }

    public function removeMember(int $memberId): void
    {
        $membership = TeamMember::where('trainer_id', $this->trainer()->id)
            ->where('member_id', $memberId)
            ->firstOrFail();

        // เก็บประวัติไว้ ไม่ลบทิ้ง เพราะการจองเก่ายังอ้างถึงอยู่
        $membership->update(['status' => 'left', 'left_at' => now()]);

        // ออกจากทีมแล้วก็ต้องหลุดจากทุกกลุ่มของเทรนเนอร์คนนี้ด้วย
        foreach ($this->trainer()->memberGroups as $group) {
            $group->members()->detach($memberId);
        }

        $this->dispatch('toast',
            tone: 'success',
            title: 'นำออกจากทีมแล้ว',
            body: 'ประวัติการจองเดิมยังอยู่ครบ',
        );

        $this->refreshGroups();
        unset($this->members);
    }

    protected function refreshGroups(): void
    {
        unset($this->groups, $this->managingGroup, $this->assignableMembers, $this->members);
    }

    public function render()
    {
        return view('livewire.trainer.team-roster')->layout('layouts.app');
    }
}
