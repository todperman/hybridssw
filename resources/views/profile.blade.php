<x-app-layout>
    @php($user = auth()->user())

    <x-page-hero :eyebrow="$user->role?->label()" :title="$user->displayName()" pattern="stopwatch" pattern-alt="kettlebell"
                 :subtitle="$user->branch?->name ?? $user->email">
        <x-slot:action>
            <x-avatar :user="$user" size="h-14 w-14" text="text-xl" class="ring-2 ring-white/30" />
        </x-slot:action>

        <x-slot:stats>
            <dl class="mt-5 grid max-w-md grid-cols-2 gap-2 sm:gap-3">
                <div class="rounded-2xl border border-white/10 bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                    <dt class="text-[11px] leading-tight text-white/65">อีเมล</dt>
                    <dd class="mt-0.5 truncate text-[13px] font-medium text-paper">{{ $user->email }}</dd>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                    <dt class="text-[11px] leading-tight text-white/65">เข้าใช้งานล่าสุด</dt>
                    <dd class="mt-0.5 text-[13px] font-medium text-paper">
                        {{ $user->last_login_at?->locale('th')->isoFormat('D MMM YY · HH:mm') ?? 'ครั้งนี้' }}
                    </dd>
                </div>
            </dl>
        </x-slot:stats>
    </x-page-hero>

    <div class="mx-auto max-w-3xl space-y-5 px-4 pb-16 pt-6 sm:px-6 lg:px-8">

        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">ข้อมูลโปรไฟล์</h2>
                <p class="mt-0.5 text-[13px] text-muted">ชื่อที่แสดงให้เทรนเนอร์และเพื่อนร่วมรอบเห็น</p>
            </div>

            <div class="p-5">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head">
                <h2 class="font-display text-[17px] font-bold text-grad">รหัสผ่าน</h2>
                <p class="mt-0.5 text-[13px] text-muted">ตั้งรหัสที่ยาวและเดายาก จะได้ไม่ต้องเปลี่ยนบ่อย</p>
            </div>

            <div class="p-5">
                <livewire:profile.update-password-form />
            </div>
        </div>

        {{-- การ์ดอันตรายใช้โทนแดงทั้งใบ ให้แยกออกจากสองใบบนด้วยสายตาตั้งแต่ยังไม่อ่าน --}}
        <div class="overflow-hidden rounded-lg2 border border-red-200 bg-red-50/60 shadow-soft backdrop-blur-sm">
            <div class="border-b border-red-200/70 px-5 py-3.5">
                <h2 class="flex items-center gap-2 font-display text-[17px] font-bold text-red-800">
                    @svg('lucide-triangle-alert', 'h-4 w-4', ['stroke-width' => '2'])
                    ลบบัญชี
                </h2>
                <p class="mt-0.5 text-[13px] text-red-700/80">ทำแล้วย้อนกลับไม่ได้</p>
            </div>

            <div class="p-5">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
