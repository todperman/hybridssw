<?php

namespace App\Livewire;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Rules\ThaiPhone;
use App\Support\RegistrationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Component;

/**
 * คนทั่วไปสมัครสมาชิกเอง ไม่ต้องผ่านลิงก์ชวนของเทรนเนอร์
 *
 * สมาชิกแบบนี้ไม่สังกัดทีมใด จองยิมให้ตัวเองและเพื่อนได้ที่หน้าจองยิม
 * ถ้าเปิด gym.registration.member_approval ต้องรอแอดมินอนุมัติบัญชีก่อน
 */
class MemberRegistration extends Component
{
    public ?int $branch_id = null;

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

    public function mount(): void
    {
        abort_unless(RegistrationRules::publicRegistrationOpen(), 404);

        // มีสาขาเดียวก็ผูกให้เลย ไม่ต้องให้เลือก
        $this->branch_id = Branch::active()->value('id');
    }

    public function showsBranchSelector(): bool
    {
        return (bool) config('gym.registration.show_branch_selector', true)
            && Branch::active()->count() > 1;
    }

    public function branches()
    {
        return Branch::active()->orderBy('name')->pluck('name', 'id');
    }

    public function register(): void
    {
        abort_unless(RegistrationRules::publicRegistrationOpen(), 404);

        $data = $this->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
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
            'branch_id.required' => 'กรุณาเลือกสาขา',
            'accept.accepted' => 'กรุณายืนยันว่าข้อมูลที่กรอกเป็นความจริง',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'branch_id' => $data['branch_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nickname' => $data['nickname'] ?: null,
                'email' => $data['email'],
                'phone' => ThaiPhone::digits($data['phone']),
                'password' => $data['password'],
                'role' => UserRole::Member,
            ]);

            Member::create([
                'user_id' => $user->id,
                'branch_id' => $data['branch_id'],
                'primary_trainer_id' => null,
                'status' => RegistrationRules::memberRegistrationNeedsApproval() ? MemberStatus::Pending : MemberStatus::Active,
                'approved_at' => RegistrationRules::memberRegistrationNeedsApproval() ? null : now(),
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_phone' => ThaiPhone::digits($data['emergency_contact_phone']),
            ]);

            return $user;
        });

        auth()->login($user);

        $this->redirect(route(RegistrationRules::memberRegistrationNeedsApproval() ? 'member.pending' : 'member.book'), navigate: true);
    }

    public function render()
    {
        return view('livewire.member-registration')->layout('layouts.auth', [
            'eyebrow' => 'สมัครสมาชิก',
            'heading' => 'เริ่มเทรน',
            'headingAccent' => 'กับเราวันนี้',
            'lead' => 'สมัครครั้งเดียว แล้วจองยิมทั้งยิมเป็นรายชั่วโมงได้เอง',
            'altHref' => route('login'),
            'altLabel' => 'มีบัญชีอยู่แล้ว',
            'altCta' => 'เข้าสู่ระบบ',
            'wide' => true,
        ]);
    }
}
