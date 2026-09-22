<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use App\Services\AvatarService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $first_name = '';
    public string $last_name = '';
    public string $nickname = '';
    public string $email = '';
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->first_name = Auth::user()->first_name;
        $this->last_name = Auth::user()->last_name;
        $this->nickname = Auth::user()->nickname ?? '';
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['image', 'max:4096']], attributes: ['photo' => 'รูปโปรไฟล์']);

        app(AvatarService::class)->store(Auth::user(), $this->photo);

        $this->reset('photo');
        $this->dispatch('toast', tone: 'success', title: 'อัปเดตรูปโปรไฟล์แล้ว');
    }

    public function removePhoto(): void
    {
        app(AvatarService::class)->remove(Auth::user());

        $this->dispatch('toast', tone: 'success', title: 'ลบรูปโปรไฟล์แล้ว');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <form wire:submit="updateProfileInformation" class="space-y-6">
        <div>
            {{-- รูปโปรไฟล์: อัปโหลดแล้วบันทึกทันที ไม่ต้องกดบันทึกซ้ำ --}}
            <div class="mb-6 flex items-center gap-4">
                <x-avatar :user="auth()->user()" size="h-20 w-20" text="text-2xl" class="ring-2 ring-line" />

                <div class="flex flex-wrap items-center gap-2">
                    <label class="btn-ghost cursor-pointer">
                        <input type="file" wire:model="photo" accept="image/*" class="sr-only">
                        <span wire:loading.remove wire:target="photo">เลือกรูป</span>
                        <span wire:loading wire:target="photo">กำลังอัปโหลด…</span>
                    </label>

                    @if (auth()->user()->avatar_path)
                        {{-- ไฟล์รูปถูกลบออกจากดิสก์จริง กู้คืนไม่ได้ จึงถามยืนยันก่อนเหมือนการลบอื่น ๆ --}}
                        <button type="button" class="text-[13px] text-red-700 hover:underline"
                                @click="$store.confirm.ask({
                                    title: 'ลบรูปโปรไฟล์',
                                    body: 'รูปจะถูกลบถาวร กู้คืนไม่ได้',
                                    notes: ['กลับไปใช้อักษรย่อชื่อแทน', 'อัปโหลดรูปใหม่ได้ทุกเมื่อ'],
                                    tone: 'danger',
                                    confirmLabel: 'ลบรูป',
                                    cancelLabel: 'เก็บไว้ก่อน',
                                    action: () => $wire.removePhoto(),
                                })">ลบรูป</button>
                    @endif

                    <p class="w-full text-[12px] text-muted">ไฟล์ภาพ ขนาดไม่เกิน 4 MB</p>
                    <x-input-error :messages="$errors->get('photo')" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="first_name" :value="'ชื่อ'" />
                    <x-text-input wire:model="first_name" id="first_name" name="first_name" type="text" class="mt-1 block w-full" required autofocus autocomplete="given-name" />
                    <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                </div>

                <div>
                    <x-input-label for="last_name" :value="'นามสกุล'" />
                    <x-text-input wire:model="last_name" id="last_name" name="last_name" type="text" class="mt-1 block w-full" required autocomplete="family-name" />
                    <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="nickname" :value="'ชื่อเล่น'" />
                    <x-text-input wire:model="nickname" id="nickname" name="nickname" type="text" class="mt-1 block w-full" autocomplete="nickname" />
                    <p class="mt-1 text-[12px] text-muted">ใช้เรียกหน้างาน ถ้าเว้นไว้จะแสดงชื่อจริงแทน</p>
                    <x-input-error class="mt-2" :messages="$errors->get('nickname')" />
                </div>
            </div>
        </div>

        <div>
            <x-input-label for="email" :value="'อีเมล'" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-ink">
                        {{ __('อีเมลของคุณยังไม่ได้ยืนยัน') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-muted hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-deep/40">
                            {{ __('กดที่นี่เพื่อส่งอีเมลยืนยันอีกครั้ง') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-brand-dark">
                            {{ __('ส่งลิงก์ยืนยันใหม่ไปที่อีเมลของคุณแล้ว') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ 'บันทึก' }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ 'บันทึกแล้ว' }}
            </x-action-message>
        </div>
    </form>
</section>
