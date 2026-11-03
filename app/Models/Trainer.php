<?php

namespace App\Models;

use App\Enums\TrainerStatus;
use App\Enums\TrainerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Trainer extends Model
{
    use HasFactory, SoftDeletes;

    /** ค่าเริ่มต้นตรงกับฐานข้อมูล อ็อบเจกต์ที่เพิ่งสร้างจะได้ไม่เห็นเป็น null แล้วถูกนับว่าปิดรับงาน */
    protected $attributes = [
        'accepts_bookings' => true,
    ];

    protected $fillable = [
        'user_id',
        'branch_id',
        'code',
        'type',
        'gender',
        'status',
        'accepts_bookings',
        'bio',
        'specialties',
        'certification_name',
        'certification_file_path',
        'certification_expires_at',
        'contract_starts_at',
        'contract_ends_at',
        'max_team_size',
        'invite_token',
        'approved_at',
        'approved_by',
        'review_note',
        'bank_code',
        'bank_account_number',
        'bank_account_name',
        'promptpay_id',
        'bank_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrainerType::class,
            'status' => TrainerStatus::class,
            'specialties' => 'array',
            'certification_expires_at' => 'date',
            'contract_starts_at' => 'date',
            'contract_ends_at' => 'date',
            'approved_at' => 'datetime',
            'bank_updated_at' => 'datetime',
            'accepts_bookings' => 'boolean',
        ];
    }

    public function availabilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainerAvailability::class);
    }

    public function timeOffs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainerTimeOff::class);
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** รับงานจองได้: อนุมัติแล้ว เปิดรับงาน สัญญายังมีผล และใบรับรองยังไม่หมดอายุ */
    public function canTakeReservations(): bool
    {
        return $this->isApproved()
            && $this->accepts_bookings
            && $this->isContractActive()
            && ! $this->hasCertificationExpired();
    }

    protected static function booted(): void
    {
        static::creating(function (self $trainer) {
            $trainer->code ??= 'TR-'.strtoupper(Str::random(6));
            $trainer->invite_token ??= Str::random(40);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** ลูกทีมที่ยังอยู่ในทีม */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_members')
            ->withPivot(['status', 'joined_at', 'left_at', 'joined_via', 'trainer_note'])
            ->withTimestamps()
            ->wherePivot('status', 'active');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(TrainerEarning::class);
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** กลุ่มลูกทีมที่เทรนเนอร์คนนี้สร้างไว้ */
    public function memberGroups(): HasMany
    {
        return $this->hasMany(MemberGroup::class);
    }

    /**
     * เพดานจำนวนลูกทีม คืน null = ไม่จำกัด
     *
     * ค่าในคอลัมน์มีสามความหมาย
     *   null = ใช้ค่ามาตรฐานตามประเภทเทรนเนอร์
     *   0    = ตั้งใจให้ไม่จำกัดเฉพาะคนนี้ แม้ประเภทจะมีเพดานก็ตาม
     *   > 0  = เพดานที่กำหนดเอง
     *
     * ใช้ 0 เป็นตัวแทนของ "ไม่จำกัด" เพราะทีมขนาด 0 คนไม่มีความหมายอยู่แล้ว
     * และทำให้ยังแยกออกจาก null ที่แปลว่า "ตามค่ามาตรฐาน" ได้
     */
    public function maxTeamSize(): ?int
    {
        if ($this->max_team_size === null) {
            return $this->type->defaultMaxTeamSize();
        }

        return (int) $this->max_team_size === 0 ? null : (int) $this->max_team_size;
    }

    public function hasUnlimitedTeam(): bool
    {
        return $this->maxTeamSize() === null;
    }

    /** ข้อความแสดงเพดานทีม ใช้ร่วมกันหลายหน้า */
    public function teamLimitLabel(): string
    {
        return $this->hasUnlimitedTeam() ? 'ไม่จำกัด' : (string) $this->maxTeamSize();
    }

    /** แอดมินอนุมัติ เริ่มรับงานและจองให้ลูกเทรนได้ทันที พร้อมแจ้งเทรนเนอร์ทางกระดิ่งและอีเมล */
    public function approve(?User $by = null): void
    {
        $this->update([
            'status' => TrainerStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $by?->id,
            'review_note' => null,
        ]);

        AuditLog::record('trainer.approved', $this, null, null, null, $by);
        app(\App\Services\Notify\AccountNotifier::class)->trainerApproved($this);
    }

    /** แอดมินไม่อนุมัติ เหตุผลแสดงที่หน้าสถานะใบสมัครและส่งให้เทรนเนอร์ทราบ */
    public function reject(?User $by, string $note): void
    {
        $this->update([
            'status' => TrainerStatus::Rejected,
            'review_note' => $note,
            'approved_by' => $by?->id,
        ]);

        AuditLog::record('trainer.rejected', $this, null, null, $note, $by);
        app(\App\Services\Notify\AccountNotifier::class)->trainerRejected($this, $note);
    }

    public function bank(): ?\App\Enums\ThaiBank
    {
        return $this->bank_code ? \App\Enums\ThaiBank::tryFrom($this->bank_code) : null;
    }

    public function hasBankAccount(): bool
    {
        return $this->bank() !== null && filled($this->bank_account_number);
    }

    /** มีปลายทางให้โอนได้ (บัญชีธนาคาร หรืออย่างน้อยพร้อมเพย์) */
    public function canReceivePayout(): bool
    {
        return $this->hasBankAccount() || filled($this->promptpay_id);
    }

    /** กฎตรวจบัญชีชุดเดียว ใช้ทั้งหน้าเทรนเนอร์และหลังบ้าน */
    public static function bankRules(?string $bankCode): array
    {
        $bank = $bankCode ? \App\Enums\ThaiBank::tryFrom($bankCode) : null;
        [$min, $max] = $bank?->accountDigits() ?? [10, 15];

        return [
            'bank_code' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Enums\ThaiBank::options()))],
            'bank_account_number' => ['required', 'regex:/^[0-9\- ]+$/', function ($attr, $value, $fail) use ($min, $max) {
                $digits = preg_replace('/\D+/', '', (string) $value);
                if (strlen($digits) < $min || strlen($digits) > $max) {
                    $fail($min === $max ? "เลขบัญชีต้องมี {$min} หลัก" : "เลขบัญชีต้องมี {$min}–{$max} หลัก");
                }
            }],
            'bank_account_name' => ['required', 'string', 'max:120'],
            'promptpay_id' => ['nullable', 'regex:/^[0-9\- ]+$/', function ($attr, $value, $fail) {
                $d = preg_replace('/\D+/', '', (string) $value);
                if ($d !== '' && ! in_array(strlen($d), [10, 13], true)) {
                    $fail('พร้อมเพย์ต้องเป็นเบอร์โทร 10 หลัก หรือเลขบัตรประชาชน 13 หลัก');
                }
            }],
        ];
    }

    /** "กสิกรไทย 123-4-56789-0 · ชื่อบัญชี" ไว้โชว์ให้แอดมินตอนโอน */
    public function bankLabel(): ?string
    {
        if (! $this->hasBankAccount()) {
            return $this->promptpay_id ? 'พร้อมเพย์ '.$this->promptpay_id : null;
        }

        return $this->bank()->label().' '.\App\Enums\ThaiBank::formatAccount($this->bank_account_number)
            .($this->bank_account_name ? ' · '.$this->bank_account_name : '')
            .($this->promptpay_id ? ' · พร้อมเพย์ '.$this->promptpay_id : '');
    }

    /**
     * เทรนเนอร์แก้บัญชีรับเงินเอง: ลงประวัติและแจ้งแอดมินทุกครั้ง (กันคนอื่นแอบเปลี่ยนปลายทางเงิน)
     *
     * @param  array{bank_code: ?string, bank_account_number: ?string, bank_account_name: ?string, promptpay_id: ?string}  $data
     */
    public function updateBankAccount(array $data, User $actor): void
    {
        $before = ['bank' => $this->bankLabel()];
        $this->fill([
            'bank_code' => $data['bank_code'] ?: null,
            'bank_account_number' => preg_replace('/\D+/', '', (string) ($data['bank_account_number'] ?? '')) ?: null,
            'bank_account_name' => trim((string) ($data['bank_account_name'] ?? '')) ?: null,
            'promptpay_id' => preg_replace('/\D+/', '', (string) ($data['promptpay_id'] ?? '')) ?: null,
            'bank_updated_at' => now(),
        ])->save();

        if ($before['bank'] === $this->bankLabel()) {
            return;
        }

        AuditLog::record('trainer.bank_changed', $this, $before, ['bank' => $this->bankLabel()], null, $actor);

        app(\App\Services\Notify\AccountNotifier::class)->trainerBankChanged($this, $actor);
    }

    public function genderLabel(): ?string
    {
        return \App\Enums\Gender::labelFor($this->gender);
    }

    public function isApproved(): bool
    {
        return $this->status === TrainerStatus::Approved;
    }

    /** ใบรับรองหมดอายุแล้วหรือยัง (เฉพาะเทรนเนอร์ภายนอกที่บังคับ) */
    public function hasCertificationExpired(): bool
    {
        if (! $this->type->requiresCertification()) {
            return false;
        }

        return $this->certification_expires_at !== null
            && $this->certification_expires_at->isPast();
    }

    /** สัญญายังมีผลอยู่หรือไม่ */
    public function isContractActive(): bool
    {
        $today = now()->toDateString();

        if ($this->contract_starts_at && $this->contract_starts_at->toDateString() > $today) {
            return false;
        }

        if ($this->contract_ends_at && $this->contract_ends_at->toDateString() < $today) {
            return false;
        }

        return true;
    }

    public function inviteUrl(): string
    {
        return route('team.join', ['token' => $this->invite_token]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', TrainerStatus::Approved->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', TrainerStatus::Pending->value);
    }
}
