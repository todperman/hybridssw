<?php

namespace App\Livewire\Member;

use App\Enums\MemberStatus;
use App\Models\Member;
use Livewire\Component;

/**
 * หน้าสถานะการสมัครของสมาชิกที่สมัครเอง
 * ยังไม่อนุมัติจะเข้าหน้าจองไม่ได้ จึงต้องบอกให้ชัดว่ากำลังรออะไร
 */
class PendingApproval extends Component
{
    public function member(): Member
    {
        return auth()->user()->member->loadMissing('branch', 'user');
    }

    public function headline(): string
    {
        return $this->member()->status === MemberStatus::Rejected
            ? 'การสมัครยังไม่ผ่าน'
            : 'รอแอดมินอนุมัติ';
    }

    public function lead(): string
    {
        return $this->member()->status === MemberStatus::Rejected
            ? 'แอดมินยังไม่อนุมัติการสมัครนี้ ดูเหตุผลด้านล่าง แล้วติดต่อแอดมินเพื่อแก้ไขข้อมูลได้'
            : 'การสมัครของคุณส่งถึงแอดมินแล้ว เมื่ออนุมัติแล้วคุณจะจองยิมได้ทันที';
    }

    public function render()
    {
        return view('livewire.member.pending-approval')->layout('layouts.app');
    }
}
