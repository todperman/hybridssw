<?php

namespace App\Livewire;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\TeamMember;
use App\Models\Trainer;
use App\Models\User;
use App\Rules\ThaiPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Component;

/**
 * ลูกทีมสมัครเข้าทีมเองผ่านลิงก์/QR ที่เทรนเนอร์แชร์
 * เทรนเนอร์จึงไม่ต้องกรอกข้อมูลแทนทีละคน
 */
class TeamJoin extends Component
{
    public Trainer $trainer;

    public string $first_name = '';
    public string $last_name = '';
    public string $nickname = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';

    public bool $accept = false;


    public function mount(string $token): void
    {
        $this->trainer = Trainer::where('invite_token', $token)
            ->approved()
            ->with(['user', 'branch'])
            ->firstOrFail();
    }

    public function join(): void
    {
        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc,filter', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'max:30', new ThaiPhone],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:30', new ThaiPhone],
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'กรุณายืนยันว่าข้อมูลที่กรอกเป็นความจริง',
        ]);

        // ทีมเต็มแล้วกันไว้ก่อน ไม่ให้สมัครค้างไว้เฉยๆ
        // เทรนเนอร์ที่ตั้งเป็นไม่จำกัดจะข้ามการตรวจนี้
        $limit = $this->trainer->maxTeamSize();

        if ($limit !== null && $this->trainer->teamMembers()->count() >= $limit) {
            $this->addError('accept', 'ทีมนี้เต็มแล้ว กรุณาติดต่อเทรนเนอร์');

            return;
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'branch_id' => $this->trainer->branch_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nickname' => $data['nickname'] ?: null,
                'email' => $data['email'],
                'phone' => ThaiPhone::digits($data['phone']),
                'password' => $data['password'],
                'role' => UserRole::Member,
            ]);

            $member = Member::create([
                'user_id' => $user->id,
                'branch_id' => $this->trainer->branch_id,
                'primary_trainer_id' => $this->trainer->id,
                'status' => MemberStatus::Active,
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_phone' => ThaiPhone::digits($data['emergency_contact_phone']),
            ]);

            TeamMember::create([
                'trainer_id' => $this->trainer->id,
                'member_id' => $member->id,
                'status' => 'active',
                'joined_at' => now(),
                'joined_via' => 'invite_link',
            ]);

            return $user;
        });

        auth()->login($user);

        $this->redirect(route('member.bookings'), navigate: true);
    }

    public function render()
    {
        return view('livewire.team-join')->layout('layouts.auth', [
            'eyebrow' => 'คำเชิญเข้าทีม',
            'heading' => 'เข้าร่วมทีม',
            'headingAccent' => $this->trainer->user->name,
            'lead' => $this->trainer->bio ?: 'กรอกข้อมูลครั้งเดียว แล้วเทรนเนอร์จะจองรอบให้คุณได้ทันที',
            'points' => [
                'เทรนเนอร์จองชั่วโมงให้คุณ ไม่ต้องจองเอง',
                'ดูคิวที่กำลังจะถึงและยกเลิกได้จากหน้าคิวของฉัน',
                'รอบเต็มก็ต่อคิวสำรองได้ ระบบเลื่อนคิวให้อัตโนมัติ',
            ],
            'stats' => [
                'ที่นั่งต่อรอบ' => '5',
                'ลูกทีมในทีมนี้' => $this->trainer->teamMembers()->count(),
            ],
            'altHref' => route('login'),
            'altLabel' => 'มีบัญชีอยู่แล้ว',
            'altCta' => 'เข้าสู่ระบบ',
            'wide' => true,
        ]);
    }
}
