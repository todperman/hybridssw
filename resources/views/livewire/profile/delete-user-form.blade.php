<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-4">
    <p class="text-[13px] leading-relaxed text-red-800/90">เมื่อลบบัญชีแล้ว ข้อมูลทั้งหมดจะถูกลบถาวร กรุณาบันทึกข้อมูลที่ต้องการเก็บไว้ก่อนลบ</p>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ 'ลบบัญชี' }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-ink">
                {{ __('ยืนยันว่าต้องการลบบัญชีใช่หรือไม่') }}
            </h2>

            <p class="mt-1 text-sm text-muted">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ 'รหัสผ่าน' }}" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ 'รหัสผ่าน' }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ 'ยกเลิก' }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ 'ลบบัญชี' }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
