<?php

namespace App\Livewire\Trainer;

use App\Enums\TrainerStatus;
use App\Models\Trainer;
use Livewire\Component;

/**
 * หน้ารอผลอนุมัติของเทรนเนอร์
 *
 * เทรนเนอร์ที่ยังไม่ผ่านการอนุมัติจะถูกพามาที่นี่แทนหน้าจอง
 * เพราะหน้าจองใช้อะไรไม่ได้เลย ปล่อยให้เข้าไปเจอแถบเตือนเฉย ๆ
 * ทำให้คนสมัครไม่รู้ว่าต้องทำอะไรต่อ
 */
class PendingApproval extends Component
{
    public function trainer(): Trainer
    {
        return auth()->user()->trainer->loadMissing('branch');
    }

    /** ข้อความอธิบายต่างกันตามสถานะ ไม่ใช่ทุกคนที่มารอเฉย ๆ */
    public function headline(): string
    {
        return match ($this->trainer()->status) {
            TrainerStatus::Pending => 'รอแอดมินอนุมัติ',
            TrainerStatus::Rejected => 'ใบสมัครยังไม่ผ่าน',
            TrainerStatus::Suspended => 'บัญชีถูกระงับชั่วคราว',
            TrainerStatus::Approved => 'อนุมัติแล้ว',
        };
    }

    public function lead(): string
    {
        return match ($this->trainer()->status) {
            TrainerStatus::Pending => 'ใบสมัครของคุณส่งถึงแอดมินแล้ว ปกติใช้เวลาตรวจไม่นาน เมื่ออนุมัติแล้วคุณจะเริ่มสร้างทีมและจองรอบให้ลูกทีมได้ทันที',
            TrainerStatus::Rejected => 'แอดมินยังไม่อนุมัติใบสมัครนี้ ดูเหตุผลด้านล่างแล้วติดต่อแอดมินเพื่อแก้ไขข้อมูลได้',
            TrainerStatus::Suspended => 'บัญชีนี้ถูกระงับการใช้งานชั่วคราว กรุณาติดต่อแอดมินเพื่อสอบถามรายละเอียด',
            TrainerStatus::Approved => 'บัญชีของคุณพร้อมใช้งานแล้ว',
        };
    }

    public function render()
    {
        return view('livewire.trainer.pending-approval')
            ->layout('layouts.app');
    }
}
