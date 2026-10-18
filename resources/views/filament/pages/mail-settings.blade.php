<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled">บันทึก</x-filament::button>
            <span class="text-sm" style="color: var(--gray-500)">สถานะตอนนี้: {{ $this->statusLine() }}</span>
        </div>
    </form>

    <x-filament::section heading="เหตุการณ์ที่ระบบส่งอีเมล" description="ทุกฉบับแจ้งในกระดิ่งบนเว็บด้วย และส่งสำเนาเข้าอีเมลผู้ดูแลหนึ่งฉบับต่อเหตุการณ์">
        <div class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <div>
                <p class="font-semibold">บัญชี</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5" style="color: var(--gray-600)">
                    <li>สมัครสมาชิก / สมัครเทรนเนอร์: ต้อนรับผู้สมัคร และแจ้งผู้ดูแลให้ตรวจอนุมัติ</li>
                    <li>ลูกทีมสมัครผ่านลิงก์ของเทรนเนอร์: ต้อนรับลูกทีม และแจ้งเทรนเนอร์</li>
                    <li>อนุมัติ / ไม่อนุมัติ สมาชิกหรือเทรนเนอร์</li>
                    <li>ตั้งรหัสผ่านใหม่ (ลืมรหัสผ่าน)</li>
                </ul>
            </div>
            <div>
                <p class="font-semibold">การจองและเงิน</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5" style="color: var(--gray-600)">
                    <li>จองแล้วรอชำระ · ชำระสำเร็จ ยืนยันการจอง · ยืนยันด้วยสิทธิ์ฟรี</li>
                    <li>รายการจองหมดอายุ (ไม่ชำระในเวลา)</li>
                    <li>เลื่อนวันเวลา · คำขอเลื่อน / ขอยกเลิกรับงาน และผลอนุมัติ</li>
                    <li>เปลี่ยน Trainer · ยกเลิกการจอง · คืนเงินสำเร็จ · รายการชำระที่ต้องตรวจสอบ</li>
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
