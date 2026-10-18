<?php

namespace App\Services\Notify;

use App\Models\Member;
use App\Models\Trainer;
use App\Models\User;
use App\Notifications\AccountNotice;

/**
 * แจ้งเตือนเรื่องบัญชี: สมัครใหม่ (ถึงผู้สมัครและผู้ดูแล) อนุมัติ/ไม่อนุมัติ และลูกทีมเข้าทีม
 * ทุกเหตุการณ์มีสำเนาเข้ากล่องอีเมลผู้ดูแลหนึ่งฉบับ ยกเว้นเมลต้อนรับที่ผู้ดูแลได้ฉบับ "มีคนสมัคร" อยู่แล้ว
 */
class AccountNotifier extends Notifier
{
    public function memberRegistered(User $user): void
    {
        $member = $user->member;
        $pending = $member?->awaitsApproval() ?? false;

        $this->deliver(
            new AccountNotice(
                'ยินดีต้อนรับสู่ '.config('app.name'),
                $pending
                    ? 'สมัครสมาชิกเรียบร้อยแล้ว เจ้าหน้าที่กำลังตรวจสอบข้อมูล เมื่ออนุมัติแล้วระบบจะแจ้งให้ทราบอีกครั้ง จากนั้นจองยิมได้ทันที'
                    : 'สมัครสมาชิกเรียบร้อยแล้ว เข้าสู่ระบบเพื่อดูปฏิทินความว่างและจองยิมได้เลย',
                route($pending ? 'member.pending' : 'member.book'),
            ),
            [$user],
            copyAdmin: false,
        );

        $this->deliver(
            new AccountNotice(
                $pending ? 'สมาชิกใหม่รอการอนุมัติ' : 'มีสมาชิกใหม่สมัครเข้ามา',
                $this->who($user)
                    .($member?->benefit_claim ? ' · แจ้งว่าเป็น '.$member->benefit_claim.' (กำหนดประเภทสมาชิกหลังอนุมัติ)' : '')
                    .($pending ? ' · กรุณาตรวจสอบและอนุมัติที่หลังบ้าน' : ''),
                url('/admin/members'),
            ),
            $user->branch ? $this->admins($user->branch) : [],
            context: ['user' => $user->id],
        );
    }

    public function trainerRegistered(User $user): void
    {
        $trainer = $user->trainer;
        $approved = $trainer?->isApproved() ?? false;

        $this->deliver(
            new AccountNotice(
                'ยินดีต้อนรับสู่ '.config('app.name'),
                $approved
                    ? 'สมัครเป็นเทรนเนอร์เรียบร้อยและได้รับอนุมัติแล้ว ตั้งเวลาว่าง ชวนลูกทีม และจองยิมให้ลูกเทรนได้ทันที'
                    : 'สมัครเป็นเทรนเนอร์เรียบร้อยแล้ว เจ้าหน้าที่กำลังตรวจสอบเอกสาร เมื่ออนุมัติแล้วระบบจะแจ้งให้ทราบอีกครั้ง ระหว่างนี้แก้ไขโปรไฟล์ได้',
                route($approved ? 'trainer.jobs' : 'trainer.pending'),
            ),
            [$user],
            copyAdmin: false,
        );

        $this->deliver(
            new AccountNotice(
                $approved ? 'มีเทรนเนอร์ใหม่สมัครเข้ามา' : 'เทรนเนอร์ใหม่รอการตรวจสอบ',
                $this->who($user)
                    .' · ประเภท '.($trainer?->type?->label() ?? '-')
                    .($trainer?->certification_name ? ' · ใบรับรอง '.$trainer->certification_name : '')
                    .($approved ? ' · อนุมัติอัตโนมัติแล้ว' : ' · กรุณาตรวจเอกสารและอนุมัติที่หลังบ้าน'),
                url('/admin/trainers'),
            ),
            $user->branch ? $this->admins($user->branch) : [],
            context: ['user' => $user->id],
        );
    }

    public function teamMemberJoined(User $user, Trainer $trainer): void
    {
        $trainerName = $trainer->user?->displayName() ?? 'เทรนเนอร์';

        $this->deliver(
            new AccountNotice(
                'ยินดีต้อนรับสู่ '.config('app.name'),
                'คุณเข้าร่วมทีมของ '.$trainerName.' เรียบร้อยแล้ว ใช้งานได้ทันที ดูปฏิทินความว่างและจองยิมได้เลย หรือรอให้เทรนเนอร์จองให้',
                route('member.reservations'),
            ),
            [$user],
            copyAdmin: false,
        );

        $this->deliver(
            new AccountNotice(
                'ลูกทีมใหม่เข้าทีมของคุณ',
                $this->who($user).' สมัครผ่านลิงก์คำเชิญและเข้าทีมแล้ว',
                route('trainer.team'),
            ),
            [$trainer->user],
            context: ['user' => $user->id, 'trainer' => $trainer->id],
        );
    }

    public function memberApproved(Member $member): void
    {
        $this->deliver(
            new AccountNotice(
                'อนุมัติการสมัครแล้ว',
                'บัญชีของคุณพร้อมใช้งาน เลือกวันเวลาที่ว่างจากปฏิทินแล้วจองยิมได้เลย',
                route('member.book'),
            ),
            [$member->user],
            context: ['member' => $member->id],
        );
    }

    public function memberRejected(Member $member, string $note): void
    {
        $this->deliver(
            new AccountNotice(
                'การสมัครยังไม่ผ่านการอนุมัติ',
                'เหตุผลจากเจ้าหน้าที่: '.$note.' หากต้องการแก้ไขข้อมูลหรือสอบถาม ติดต่อเจ้าหน้าที่ได้ที่อีเมลผู้ส่ง',
                route('member.pending'),
            ),
            [$member->user],
            context: ['member' => $member->id],
        );
    }

    public function trainerApproved(Trainer $trainer): void
    {
        $this->deliver(
            new AccountNotice(
                'อนุมัติเทรนเนอร์แล้ว',
                'บัญชีเทรนเนอร์ของคุณพร้อมใช้งาน ตั้งเวลาว่างประจำสัปดาห์ ชวนลูกทีมด้วยลิงก์/QR และจองยิมให้ลูกเทรนได้ทันที',
                route('trainer.jobs'),
            ),
            [$trainer->user],
            context: ['trainer' => $trainer->id],
        );
    }

    public function trainerRejected(Trainer $trainer, string $note): void
    {
        $this->deliver(
            new AccountNotice(
                'ใบสมัครเทรนเนอร์ยังไม่ผ่านการอนุมัติ',
                'เหตุผลจากเจ้าหน้าที่: '.$note.' แก้ไขเอกสารหรือข้อมูลในโปรไฟล์แล้วติดต่อเจ้าหน้าที่เพื่อตรวจสอบอีกครั้ง',
                route('trainer.pending'),
            ),
            [$trainer->user],
            context: ['trainer' => $trainer->id],
        );
    }

    protected function who(User $user): string
    {
        return $user->displayName().' · '.($user->phone ?: 'ไม่มีเบอร์').' · '.$user->email;
    }
}
