<?php

namespace App\Livewire;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Trainer;
use App\Models\User;
use App\Rules\ThaiPhone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Component;

/**
 * สมัครเป็นเทรนเนอร์ แบ่งเป็น 3 ขั้นเพื่อไม่ให้เจอฟอร์มยาวรวดเดียว
 * ภายในอนุมัติอัตโนมัติ ภายนอกเข้าคิวรอแอดมินตรวจเอกสาร
 */
class TrainerRegistration extends Component
{
    public int $step = 1;

    // ขั้นที่ 1 — บัญชี
    public string $first_name = '';
    public string $last_name = '';
    public string $nickname = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    // ขั้นที่ 2 — ประเภทและสาขา
    public ?int $branch_id = null;
    public string $type = 'external';

    // ขั้นที่ 3 — ยืนยันตัวตน
    public string $internal_code = '';
    public string $certification_name = '';
    public ?string $certification_expires_at = null;
    public string $bio = '';

    public function mount(): void
    {
        // ผูกสาขาให้เสมอ ต่อให้ไม่ได้แสดงตัวเลือก จะได้ไม่มีเทรนเนอร์ที่ไม่มีสาขา
        $this->branch_id = Branch::active()->value('id');
    }

    public function showsBranchSelector(): bool
    {
        // มีสาขาเดียวก็ไม่ต้องให้เลือก ถึงจะเปิดค่านี้ไว้ก็ตาม
        return (bool) config('gym.registration.show_branch_selector', true)
            && Branch::active()->count() > 1;
    }

    public function requiresIdentityVerification(): bool
    {
        return (bool) config('gym.registration.require_identity_verification', true);
    }

    public function lastStep(): int
    {
        return $this->requiresIdentityVerification() ? 3 : 2;
    }

    /** @return array<int, array{title:string, hint:string}> */
    public function steps(): array
    {
        $steps = [
            1 => ['title' => 'บัญชีของคุณ', 'hint' => 'ข้อมูลติดต่อและรหัสผ่าน'],
            2 => ['title' => 'ประเภทเทรนเนอร์', 'hint' => $this->showsBranchSelector() ? 'สิทธิ์การจองและสาขา' : 'กำหนดสิทธิ์การจองของคุณ'],
        ];

        if ($this->requiresIdentityVerification()) {
            $steps[3] = $this->isInternal()
                ? ['title' => 'รหัสพนักงาน', 'hint' => 'ยืนยันว่าเป็นคนภายใน']
                : ['title' => 'ข้อมูลใบรับรอง', 'hint' => 'กรอกเท่าที่มี ไม่บังคับ'];
        }

        return $steps;
    }

    public function isInternal(): bool
    {
        return $this->type === TrainerType::Internal->value;
    }

    public function selectedType(): TrainerType
    {
        return TrainerType::tryFrom($this->type) ?? TrainerType::External;
    }

    /** กฎของแต่ละขั้น แยกไว้เพื่อให้ตรวจทีละขั้นได้ */
    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'first_name' => ['required', 'string', 'max:120'],
                'last_name' => ['required', 'string', 'max:120'],
                'nickname' => ['nullable', 'string', 'max:60'],
                'phone' => ['required', 'string', 'max:30', new ThaiPhone],
                // email:filter ใช้ FILTER_VALIDATE_EMAIL ซึ่งเข้มกว่ากฎ email เปล่า ๆ
                // กันรูปแบบที่ผ่านกฎพื้นฐานแต่ส่งจริงไม่ได้ เช่น a@b หรือมีช่องว่างคั่น
                'email' => ['required', 'string', 'email:rfc,filter', 'max:255', Rule::unique(User::class, 'email')],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ],
            2 => [
                'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
                // รับเฉพาะประเภทที่เปิดให้สมัครจริง ไม่งั้นส่งค่า internal ตรง ๆ ก็ยังผ่าน
                // ทั้งที่ตัวเลือกถูกซ่อนไว้แล้ว
                'type' => ['required', Rule::in(array_column(TrainerType::orderedForRegistration(), 'value'))],
            ],
            3 => ! $this->requiresIdentityVerification()
                ? ['bio' => ['nullable', 'string', 'max:2000']]
                : ($this->isInternal()
                ? [
                    'internal_code' => ['required'],
                    'bio' => ['nullable', 'string', 'max:2000'],
                ]
                : [
                    // ไม่บังคับกรอก แอดมินตรวจเอกสารจริงตอนพิจารณาอนุมัติอยู่แล้ว
                    // ถ้ากรอกวันหมดอายุมา ต้องเป็นวันที่ยังไม่หมดอายุ
                    'certification_name' => ['nullable', 'string', 'max:255'],
                    'certification_expires_at' => ['nullable', 'date', 'after:today'],
                    'bio' => ['nullable', 'string', 'max:2000'],
                ]),
            default => [],
        };
    }

    protected function rules(): array
    {
        return array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
        );
    }

    protected function messages(): array
    {
        return [
            'certification_expires_at.after' => 'ใบรับรองต้องยังไม่หมดอายุ',
            'internal_code.required' => 'กรุณากรอกรหัสพนักงานที่ได้รับจากแอดมิน',
            'password.confirmed' => 'รหัสผ่านทั้งสองช่องไม่ตรงกัน',
        ];
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step));

        $this->step = min($this->step + 1, $this->lastStep());
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    /** ให้กดย้อนกลับไปขั้นที่ผ่านมาแล้วได้ แต่ห้ามกระโดดข้ามไปข้างหน้า */
    public function isLastStep(): bool
    {
        return $this->step >= $this->lastStep();
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->step) {
            $this->step = max($step, 1);
        }
    }

    public function register(): void
    {
        $data = $this->validate();
        $type = TrainerType::from($data['type']);

        if ($this->requiresIdentityVerification()
            && $type === TrainerType::Internal
            && ! hash_equals(config('gym.internal_trainer_code'), $this->internal_code)) {
            $this->addError('internal_code', 'รหัสพนักงานไม่ถูกต้อง');

            return;
        }

        /*
         * อนุมัติอัตโนมัติได้ต่อเมื่อยืนยันตัวตนแล้วจริงเท่านั้น
         * ถ้าปิดการยืนยันตัวตนไว้ ใครก็อ้างว่าเป็นเทรนเนอร์ภายในได้
         * ทุกคนจึงต้องเข้าคิวรอแอดมินตรวจแทน
         */
        $autoApprove = $this->requiresIdentityVerification() && $type->isAutoApproved();

        $user = DB::transaction(function () use ($data, $type, $autoApprove) {
            $user = User::create([
                'branch_id' => $data['branch_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nickname' => $data['nickname'] ?: null,
                'email' => $data['email'],
                'phone' => ThaiPhone::digits($data['phone']),
                'password' => $data['password'],
                'role' => UserRole::Trainer,
            ]);

            Trainer::create([
                'user_id' => $user->id,
                'branch_id' => $data['branch_id'],
                'type' => $type,
                // ภายในเริ่มงานได้เลย ภายนอกต้องรอตรวจเอกสารก่อน
                'status' => $autoApprove ? TrainerStatus::Approved : TrainerStatus::Pending,
                'approved_at' => $autoApprove ? now() : null,
                'bio' => $this->bio ?: null,
                'certification_name' => $this->certification_name ?: null,
                'certification_expires_at' => $this->certification_expires_at ?: null,
            ]);

            return $user;
        });

        event(new Registered($user));
        auth()->login($user);

        // เทรนเนอร์ภายในที่ยืนยันตัวตนแล้วอนุมัติอัตโนมัติ เข้าหน้าจองได้เลย
        // ที่เหลือไปหน้ารอผลอนุมัติ จะได้รู้ว่าต้องรออะไรและตอนนี้ทำอะไรได้บ้าง
        $this->redirect(
            route($autoApprove ? 'trainer.schedule' : 'trainer.pending'),
            navigate: true,
        );
    }

    public function render()
    {
        return view('livewire.trainer-registration', [
            'branches' => Branch::active()->orderBy('name')->get(),
        ])->layout('layouts.auth', [
            'eyebrow' => 'สมัครเทรนเนอร์',
            'heading' => 'สร้างทีมของคุณ',
            'headingAccent' => 'แล้วเริ่มเทรน',
            'lead' => 'สมัครครั้งเดียว จากนั้นชวนลูกทีมเข้าทีมด้วยลิงก์หรือ QR แล้วจองรอบให้พวกเขาได้เอง',
            // ข้อความฝั่งซ้ายต้องสอดคล้องกับประเภทที่เปิดให้สมัครจริง
            // ถ้าปิดเทรนเนอร์ภายในไว้แล้วยังโฆษณารหัสพนักงาน คนอ่านจะหาไม่เจอ
            'points' => array_values(array_filter([
                config('gym.registration.allow_internal', false)
                    ? 'เทรนเนอร์ภายในใช้งานได้ทันทีด้วยรหัสพนักงาน'
                    : null,
                'สมัครครั้งเดียว รอแอดมินอนุมัติแล้วเริ่มจองได้',
                'ลูกทีมสมัครเองผ่านลิงก์ ไม่ต้องกรอกแทนทีละคน',
            ])),
            'altHref' => route('login'),
            'altLabel' => 'มีบัญชีอยู่แล้ว',
            'altCta' => 'เข้าสู่ระบบ',
            'wide' => true,
        ]);
    }
}
