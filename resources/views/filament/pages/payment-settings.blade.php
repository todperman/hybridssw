<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled">บันทึก</x-filament::button>
            <span class="text-sm" style="color: var(--gray-500)">
                ค่าที่ตั้งที่นี่มีผลทันทีและทับค่าใน .env ช่องที่เว้นว่างยังใช้ค่าจาก .env ต่อ
            </span>
        </div>
    </form>
</x-filament-panels::page>
