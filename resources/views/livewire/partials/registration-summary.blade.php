{{-- สรุปก่อนสมัคร ใช้ร่วมกันทั้งตอนที่ขั้นที่ 2 เป็นขั้นสุดท้าย (ปิดยืนยันตัวตน)
     และตอนที่มีขั้นที่ 3 จะได้ไม่ต้องดูแลสองชุดให้ข้อมูลหลุดไม่ตรงกัน --}}
<div class="fade-up d-4 overflow-hidden rounded-2xl border border-line bg-white/70">
    <div class="border-b border-line bg-mist/40 px-4 py-2.5">
        <p class="text-[13px] font-semibold text-ink">ตรวจสอบก่อนสมัคร</p>
    </div>

    <dl class="divide-y divide-line/70 text-[13px]">
        @php
            $rows = [
                ['ชื่อ-นามสกุล', trim($first_name.' '.$last_name) ?: null],
                ['ชื่อเล่น', $nickname ?: null],
                ['อีเมล', $email ?: null],
                ['เบอร์โทร', $phone ?: null],
                ['ประเภทเทรนเนอร์', $this->selectedType()->label()],
            ];

            if ($this->showsBranchSelector()) {
                $rows[] = ['สาขาประจำ', \App\Models\Branch::find($branch_id)?->name];
            }

            if ($this->requiresIdentityVerification()) {
                if ($this->isInternal()) {
                    $rows[] = ['รหัสพนักงาน', $internal_code ?: null];
                } else {
                    $rows[] = ['ชื่อใบรับรอง', $certification_name ?: null];
                    $rows[] = [
                        'วันหมดอายุใบรับรอง',
                        $certification_expires_at
                            ? \Carbon\Carbon::parse($certification_expires_at)->locale('th')->isoFormat('D MMM YYYY')
                            : null,
                    ];
                }
            }

            $rows[] = ['แนะนำตัว', $bio ?: null];
        @endphp

        @foreach ($rows as [$label, $value])
            <div class="flex items-start justify-between gap-4 px-4 py-2.5">
                <dt class="shrink-0 text-muted">{{ $label }}</dt>

                {{-- ช่องที่ไม่บังคับแล้วเว้นไว้ ต้องบอกให้ชัดว่า "ไม่ได้กรอก"
                     ไม่ใช่ปล่อยว่างจนดูเหมือนข้อมูลหาย --}}
                <dd class="min-w-0 flex-1 text-end {{ $value ? 'text-ink' : 'text-muted/60' }}">
                    {{ $value ?: 'ไม่ได้กรอก' }}
                </dd>
            </div>
        @endforeach

        <div class="flex items-start justify-between gap-4 px-4 py-2.5">
            <dt class="shrink-0 text-muted">สถานะหลังสมัคร</dt>
            <dd class="text-end font-medium {{ $this->selectedType()->isAutoApproved() && $this->requiresIdentityVerification() ? 'text-brand-deep' : 'text-accent-ink' }}">
                {{ $this->selectedType()->isAutoApproved() && $this->requiresIdentityVerification()
                    ? 'จองได้ทันที'
                    : 'รอแอดมินอนุมัติ' }}
            </dd>
        </div>
    </dl>
</div>
